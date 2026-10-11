//! Public accounts and writes use a separate pool; browse queries retain their read-only guard.
use crate::{
    model::{decode, encode},
    web::{self, AppState},
};
use axum::{
    Extension,
    extract::{Form, OriginalUri, Request, State},
    http::{HeaderMap, HeaderValue, StatusCode, header},
    middleware::Next,
    response::{IntoResponse, Redirect, Response},
};
use base64::Engine;
use hmac::{Hmac, Mac};
use lettre::{
    AsyncSmtpTransport, AsyncTransport, Message, Tokio1Executor, message::Mailbox,
    transport::smtp::authentication::Credentials,
};
use rand::RngCore;
use serde::Deserialize;
use sha2::{Digest, Sha256};
use sqlx::{FromRow, MySql, MySqlPool, Row, Transaction};
use std::{
    collections::{BTreeMap, BTreeSet},
    sync::{Arc, OnceLock},
    time::Duration,
};
use subtle::ConstantTimeEq;

const COOKIE: &str = "gameloadouts_rust_session";
#[derive(Clone, Default, Debug)]
pub struct Session {
    pub hash: String,
    pub csrf: String,
    pub email: String,
    pub username: String,
    pub role: String,
    pub votes: BTreeSet<i64>,
}
impl Session {
    pub fn member(&self) -> bool {
        !self.email.is_empty() && self.role != "Guest"
    }
}
pub struct Accounts {
    pub pool: MySqlPool,
    pub secure_cookie: bool,
    pub site_key: String,
    pub captcha_secret: String,
    pub captcha_url: String,
    pub captcha_hostname: String,
    pub disqus_secret: String,
    pub mailer: Option<AsyncSmtpTransport<Tokio1Executor>>,
    pub mail_from: Mailbox,
    pub account_url: String,
    // An isolated loopback SMTP receiver is used by integration tests; no production bypass.
}
impl Accounts {
    pub fn from_env(pool: MySqlPool) -> Result<Self, Box<dyn std::error::Error>> {
        let username = std::env::var("MAIL_USERNAME")?;
        let password = std::env::var("MAIL_PASSWORD")?;
        let host = std::env::var("MAIL_HOST").unwrap_or_else(|_| "smtp.zoho.com".into());
        let port = std::env::var("MAIL_PORT")
            .unwrap_or_else(|_| "465".into())
            .parse()?;
        let builder = match std::env::var("MAIL_ENCRYPTION")
            .unwrap_or_else(|_| "ssl".into())
            .as_str()
        {
            "ssl" => AsyncSmtpTransport::<Tokio1Executor>::relay(&host)?,
            "tls" => AsyncSmtpTransport::<Tokio1Executor>::starttls_relay(&host)?,
            _ => return Err("MAIL_ENCRYPTION must be ssl or tls".into()),
        };
        let account_url = std::env::var("ACCOUNT_BASE_URL")?;
        let url = reqwest::Url::parse(&account_url)?;
        if url.scheme() != "https"
            || url.host_str().is_none()
            || url.query().is_some()
            || url.fragment().is_some()
            || !url.username().is_empty()
            || url.password().is_some()
        {
            return Err("ACCOUNT_BASE_URL must be an HTTPS origin".into());
        }
        Ok(Self {
            pool,
            disqus_secret: std::env::var("DISQUS_SECRET_KEY").unwrap_or_default(),
            secure_cookie: true,
            site_key: std::env::var("RECAPTCHA_PUBLIC_KEY")
                .unwrap_or_else(|_| "6LdAYgITAAAAAIiGNHQnJvj0JYEW9xG7loWs7fls".into()),
            captcha_secret: std::env::var("RECAPTCHA_PRIVATE_KEY")?,
            captcha_url: "https://www.google.com/recaptcha/api/siteverify".into(),
            captcha_hostname: url.host_str().unwrap().into(),
            mailer: Some(
                builder
                    .port(port)
                    .credentials(Credentials::new(username, password))
                    .timeout(Some(Duration::from_secs(8)))
                    .build(),
            ),
            mail_from: std::env::var("MAIL_FROM")
                .unwrap_or_else(|_| "Game Loadouts <mail@gameloadouts.com>".into())
                .parse()?,
            account_url: account_url.trim_end_matches('/').into(),
        })
    }
    pub fn disqus_auth(&self, session: &Session) -> String {
        if !session.member() || self.disqus_secret.is_empty() {
            return String::new();
        }
        let message=base64::engine::general_purpose::STANDARD.encode(serde_json::json!({"id":session.email,"username":session.username,"email":session.email}).to_string());
        let timestamp = std::time::SystemTime::now()
            .duration_since(std::time::UNIX_EPOCH)
            .unwrap_or_default()
            .as_secs();
        let input = format!("{message} {timestamp}");
        let mut hmac = Hmac::<sha1::Sha1>::new_from_slice(self.disqus_secret.as_bytes())
            .expect("HMAC accepts any key length");
        hmac.update(input.as_bytes());
        let signature = hmac
            .finalize()
            .into_bytes()
            .iter()
            .map(|b| format!("{b:02x}"))
            .collect::<String>();
        format!("{message} {signature} {timestamp}")
    }
    async fn email(&self, to: &str, subject: &str, link: &str) -> Result<(), Error> {
        let mail = Message::builder()
            .from(self.mail_from.clone())
            .to(to
                .parse()
                .map_err(|_| Error::invalid("Invalid email address."))?)
            .subject(subject)
            .body(format!(
                "{subject}\n\n{link}\n\nIf you did not request this, ignore this message."
            ))
            .map_err(|_| Error::internal())?;
        self.mailer
            .as_ref()
            .ok_or_else(Error::internal)?
            .send(mail)
            .await
            .map_err(|_| Error::invalid("Email delivery failed. Please try again later."))?;
        Ok(())
    }
    async fn captcha(&self, state: &AppState, response: &str) -> Result<(), Error> {
        if response.is_empty() || response.len() > 4096 {
            return Err(Error::invalid("Complete the CAPTCHA and try again."));
        }
        #[derive(Deserialize)]
        struct Check {
            success: bool,
            #[serde(default)]
            hostname: String,
        }
        let result: Check = state
            .http
            .post(&self.captcha_url)
            .form(&[
                ("secret", self.captcha_secret.as_str()),
                ("response", response),
            ])
            .send()
            .await
            .map_err(|_| Error::internal())?
            .error_for_status()
            .map_err(|_| Error::internal())?
            .json()
            .await
            .map_err(|_| Error::internal())?;
        if !result.success || result.hostname != self.captcha_hostname {
            return Err(Error::invalid("Incorrect CAPTCHA. Please try again."));
        }
        Ok(())
    }
}
#[derive(Debug)]
pub struct Error {
    status: u16,
    message: &'static str,
}
impl Error {
    fn internal() -> Self {
        Self {
            status: 503,
            message: "The service is temporarily unavailable. Please try again.",
        }
    }
    fn invalid(message: &'static str) -> Self {
        Self {
            status: 422,
            message,
        }
    }
    fn denied() -> Self {
        Self {
            status: 403,
            message: "This form has expired. Refresh the page and try again.",
        }
    }
}
impl From<sqlx::Error> for Error {
    fn from(e: sqlx::Error) -> Self {
        if matches!(&e,sqlx::Error::Database(d) if d.is_unique_violation()) {
            Self::invalid("That username or email is already in use.")
        } else {
            match &e {
                sqlx::Error::Database(d) => tracing::error!(
                    sqlstate = d.code().as_deref().unwrap_or("unknown"),
                    mysql_code = d
                        .try_downcast_ref::<sqlx::mysql::MySqlDatabaseError>()
                        .map(|e| e.number()),
                    "Account SQL operation rejected"
                ),
                sqlx::Error::ColumnDecode { index, .. } => {
                    tracing::error!(column = index, "Account column decode failed")
                }
                sqlx::Error::ColumnNotFound(column) => {
                    tracing::error!(column, "Account column missing")
                }
                _ => tracing::error!("Account database operation failed"),
            }
            Self::internal()
        }
    }
}
pub fn random_token() -> String {
    let mut bytes = [0u8; 32];
    rand::rngs::OsRng.fill_bytes(&mut bytes);
    bytes.iter().map(|b| format!("{b:02x}")).collect()
}
pub fn digest(s: &str) -> String {
    Sha256::digest(s.as_bytes())
        .iter()
        .map(|b| format!("{b:02x}"))
        .collect()
}
fn raw_cookie(headers: &HeaderMap) -> Option<String> {
    headers
        .get(header::COOKIE)?
        .to_str()
        .ok()?
        .split(';')
        .find_map(|part| {
            let (name, value) = part.trim().split_once('=')?;
            (name == COOKIE && value.len() == 64 && value.bytes().all(|c| c.is_ascii_hexdigit()))
                .then(|| value.into())
        })
}
fn cookie(accounts: &Accounts, token: &str, ttl: i64) -> HeaderValue {
    HeaderValue::from_str(&format!(
        "{COOKIE}={token}; Path=/; HttpOnly; SameSite=Lax; Max-Age={ttl}{}",
        if accounts.secure_cookie {
            "; Secure"
        } else {
            ""
        }
    ))
    .unwrap()
}
#[derive(FromRow)]
struct SessionRow {
    token_hash: String,
    csrf: String,
    email: String,
    username: String,
    role: String,
}
async fn load_session(a: &Accounts, token: &str) -> Result<Session, Error> {
    let row=sqlx::query_as::<_,SessionRow>("SELECT s.token_hash,s.csrf,COALESCE(u.email,'') email,COALESCE(u.username,'') username,COALESCE(u.role,'') role FROM rust_sessions s LEFT JOIN users u ON u.email=s.user_email AND (u.role='Guest' OR (u.confirm_token='1' AND s.credential_hash=SHA2(u.password,256))) WHERE s.token_hash=? AND s.expires_at>UTC_TIMESTAMP() AND (s.user_email IS NULL OR u.email IS NOT NULL)") .bind(digest(token)).fetch_optional(&a.pool).await?;
    let Some(row) = row else {
        return Ok(Session::default());
    };
    let mut session = Session {
        hash: row.token_hash,
        csrf: row.csrf,
        email: row.email,
        username: row.username,
        role: row.role,
        votes: BTreeSet::new(),
    };
    if !session.email.is_empty() {
        session.votes = sqlx::query_scalar::<_, i64>(
            "SELECT CAST(loadout_id AS SIGNED) FROM loadout_user WHERE user_id=?",
        )
        .bind(&session.email)
        .fetch_all(&a.pool)
        .await?
        .into_iter()
        .collect();
    }
    Ok(session)
}
async fn issue(
    a: &Accounts,
    email: Option<&str>,
    remember: bool,
    verified_password: Option<&str>,
) -> Result<(String, Session), Error> {
    if rand::random::<u8>() < 26 {
        sqlx::query("DELETE FROM rust_sessions WHERE expires_at<=UTC_TIMESTAMP() LIMIT 128")
            .execute(&a.pool)
            .await?;
        sqlx::query("DELETE FROM rust_account_tokens WHERE expires_at<=UTC_TIMESTAMP() LIMIT 128")
            .execute(&a.pool)
            .await?;
    }
    let token = random_token();
    let csrf = random_token();
    let hash = digest(&token);
    let ttl = if remember { 30 * 24 * 3600 } else { 2 * 3600 };
    sqlx::query("INSERT INTO rust_sessions(token_hash,user_email,csrf,expires_at,created_at,credential_hash) VALUES(?,?,?,TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP()),UTC_TIMESTAMP(),COALESCE(?,(SELECT SHA2(password,256) FROM users WHERE email=?)))").bind(&hash).bind(email).bind(&csrf).bind(ttl).bind(verified_password.map(digest)).bind(email).execute(&a.pool).await?;
    Ok((
        token,
        Session {
            hash,
            csrf,
            ..Session::default()
        },
    ))
}
pub async fn session_layer(
    State(state): State<Arc<AppState>>,
    mut request: Request,
    next: Next,
) -> Response {
    let mut session = Session::default();
    let mut set_cookie = None;
    let incoming_token = raw_cookie(request.headers());
    let path = request.uri().path().to_owned();
    let is_get = request.method() == axum::http::Method::GET;
    if let Some(a) = &state.accounts {
        let result = async {
            if let Some(token) = incoming_token {
                session = load_session(a, &token).await?;
            }
            // Anonymous browsing stays read-only until a page needs an interactive form.

            let needs_form = matches!(
                path.as_str(),
                "/login" | "/join" | "/reminder" | "/resend-confirmation" | "/logout" | "/account"
            ) || path.starts_with("/password/reset/")
                || (path.trim_matches('/').split('/').count() == 2 && !path.starts_with("/user/"))
                || path.trim_matches('/').split('/').count() == 3;
            if session.hash.is_empty() && is_get && needs_form {
                let (token, new) = issue(a, None, false, None).await?;
                session = new;
                set_cookie = Some(cookie(a, &token, 7200));
            }
            Ok::<_, Error>(())
        }
        .await;
        if let Err(e) = result {
            return web::account_page(&state, &session, "info", e.status, e.message, "");
        }
    }
    request.extensions_mut().insert(session);
    let mut response = next.run(request).await;
    response
        .headers_mut()
        .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
    response.headers_mut().insert(
        header::REFERRER_POLICY,
        HeaderValue::from_static("strict-origin-when-cross-origin"),
    );
    if response.headers().get(header::SET_COOKIE).is_none()
        && let Some(value) = set_cookie
    {
        response.headers_mut().insert(header::SET_COOKIE, value);
    }
    response
}
fn csrf(session: &Session, form: &BTreeMap<String, String>) -> Result<(), Error> {
    let value = form.get("_token").map(String::as_str).unwrap_or("");
    if session.hash.is_empty()
        || session.csrf.len() != 64
        || value.len() != 64
        || !bool::from(session.csrf.as_bytes().ct_eq(value.as_bytes()))
    {
        return Err(Error::denied());
    }
    Ok(())
}
fn value<'a>(f: &'a BTreeMap<String, String>, name: &str) -> &'a str {
    f.get(name).map(String::as_str).unwrap_or("")
}
fn valid_password(f: &BTreeMap<String, String>) -> Result<String, Error> {
    let p = value(f, "password");
    if p.chars().count() < 4 || p.len() > 64 || p != value(f, "password_confirmation") {
        return Err(Error::invalid(
            "Passwords must match and contain 4–64 bytes.",
        ));
    }
    Ok(p.into())
}
static PASSWORD_WORK: OnceLock<Arc<tokio::sync::Semaphore>> = OnceLock::new();
async fn password_permit() -> Result<tokio::sync::OwnedSemaphorePermit, Error> {
    tokio::time::timeout(
        Duration::from_secs(5),
        PASSWORD_WORK
            .get_or_init(|| Arc::new(tokio::sync::Semaphore::new(4)))
            .clone()
            .acquire_owned(),
    )
    .await
    .map_err(|_| Error::internal())?
    .map_err(|_| Error::internal())
}
async fn hash_password(password: String) -> Result<String, Error> {
    let permit = password_permit().await?;
    tokio::task::spawn_blocking(move || {
        let _permit = permit;
        bcrypt::hash(password, 12)
    })
    .await
    .map_err(|_| Error::internal())?
    .map_err(|_| Error::internal())
}
async fn verify_password(password: String, hash: String) -> Result<bool, Error> {
    let permit = password_permit().await?;
    tokio::task::spawn_blocking(move || {
        let _permit = permit;
        bcrypt::verify(password, &hash)
    })
    .await
    .map_err(|_| Error::internal())?
    .map_err(|_| Error::internal())
}
async fn rotate(
    a: &Accounts,
    old: &Session,
    email: &str,
    remember: bool,
    verified_password: Option<&str>,
) -> Result<Response, Error> {
    let (token, _) = issue(a, Some(email), remember, verified_password).await?;
    if !old.hash.is_empty() {
        sqlx::query("DELETE FROM rust_sessions WHERE token_hash=?")
            .bind(&old.hash)
            .execute(&a.pool)
            .await?;
    }
    let mut response = Redirect::to("/account").into_response();
    response.headers_mut().insert(
        header::SET_COOKIE,
        cookie(a, &token, if remember { 30 * 24 * 3600 } else { 7200 }),
    );
    Ok(response)
}
pub async fn get_account(
    State(state): State<Arc<AppState>>,
    Extension(session): Extension<Session>,
    OriginalUri(uri): OriginalUri,
) -> Response {
    if state.accounts.is_none() {
        return web::account_page(
            &state,
            &session,
            "info",
            404,
            "Accounts are not enabled yet.",
            "",
        );
    }
    let path = uri.path();
    if path == "/account" && !session.member() {
        return Redirect::to("/login").into_response();
    }
    if matches!(path, "/join" | "/login") && session.member() {
        return Redirect::to("/account").into_response();
    }
    let screen = path.trim_start_matches('/');
    if let Some(token) = path.strip_prefix("/password/reset/") {
        return web::account_page(&state, &session, "reset", 200, "", token);
    }
    web::account_page(&state, &session, screen, 200, "", "")
}
pub async fn post_account(
    State(state): State<Arc<AppState>>,
    Extension(session): Extension<Session>,
    OriginalUri(uri): OriginalUri,
    Form(form): Form<BTreeMap<String, String>>,
) -> Response {
    let Some(a) = &state.accounts else {
        return StatusCode::NOT_FOUND.into_response();
    };
    let path = uri.path();
    let result=async {
        csrf(&session,&form)?;
        match path {
            "/login"=>login(a,&session,&form).await,
            "/logout"=>{sqlx::query("DELETE FROM rust_sessions WHERE token_hash=?").bind(&session.hash).execute(&a.pool).await?;let mut response=Redirect::to("/").into_response();response.headers_mut().insert(header::SET_COOKIE,cookie(a,"",0));Ok(response)},
            "/join"=>join(&state,a,&session,&form).await,
            "/reminder"|"/resend-confirmation"=>send_token(a,&session,&form,if path=="/reminder" {"reset"} else {"confirm"},&state).await,
            "/account"=>{
                if !session.member() {return Ok(Redirect::to("/login").into_response());}
                let current=value(&form,"current_password").to_owned();
                let password:String=sqlx::query_scalar("SELECT password FROM users WHERE email=?").bind(&session.email).fetch_one(&a.pool).await?;
                if current.len()>64 || !verify_password(current,password.clone()).await? {return Err(Error::invalid("Your current password is incorrect."));}
                let hash=hash_password(valid_password(&form)?).await?;
                let mut tx=a.pool.begin().await?;
                let changed=sqlx::query("UPDATE users SET password=?,remember_token='',updated_at=UTC_TIMESTAMP() WHERE email=? AND password=?").bind(hash).bind(&session.email).bind(password).execute(&mut *tx).await?.rows_affected();
                if changed!=1 {return Err(Error::invalid("Your password changed during this request. Sign in again."));}
                sqlx::query("DELETE FROM rust_sessions WHERE user_email=?").bind(&session.email).execute(&mut *tx).await?;
                sqlx::query("DELETE FROM rust_account_tokens WHERE user_email=?").bind(&session.email).execute(&mut *tx).await?;
                tx.commit().await?;rotate(a,&session,&session.email,false,None).await
            },
            "/password/reset"=>reset(a,&session,&form).await,
            _=>Ok(StatusCode::NOT_FOUND.into_response()),
        }
    }.await;
    match result {
        Ok(response) => response,
        Err(e) => web::account_page(
            &state,
            &session,
            if path == "/password/reset" {
                "reset"
            } else {
                path.trim_start_matches('/')
            },
            e.status,
            e.message,
            value(&form, "token"),
        ),
    }
}
#[derive(FromRow)]
struct LoginUser {
    email: String,
    password: String,
    confirm_token: String,
    failed_attempts: i64,
    locked: i64,
}
async fn login(
    a: &Accounts,
    session: &Session,
    f: &BTreeMap<String, String>,
) -> Result<Response, Error> {
    let name = value(f, "emailOrUsername");
    let password = value(f, "password");
    if name.len() > 128 || password.len() > 64 {
        return Err(Error::invalid(
            "Your username/email and password combination was incorrect.",
        ));
    }
    // Serialize attempts with account updates. Never verify bcrypt on an async worker thread.
    let mut tx = a.pool.begin().await?;
    let user=sqlx::query_as::<_,LoginUser>("SELECT email,password,confirm_token,CAST(failed_attempts AS SIGNED) failed_attempts,CAST(disabled_until>UTC_TIMESTAMP() AS SIGNED) locked FROM users WHERE (email=? OR username=?) AND role<>'Guest' ORDER BY email=? DESC LIMIT 1 FOR UPDATE").bind(name).bind(name).bind(name).fetch_optional(&mut *tx).await?;
    let Some(user) = user else {
        // Pay the same expensive hash cost for unknown users.
        let _ = hash_password("invalid-login-password".into()).await?;
        return Err(Error::invalid(
            "Your username/email and password combination was incorrect.",
        ));
    };
    if user.locked != 0 {
        return Err(Error::invalid(
            "Too many attempts. Please try again after 15 minutes.",
        ));
    }
    if !verify_password(password.into(), user.password.clone()).await? {
        let attempts = user.failed_attempts + 1;
        sqlx::query("UPDATE users SET failed_attempts=?,disabled_until=IF(? >= 5,TIMESTAMPADD(MINUTE,15,UTC_TIMESTAMP()),disabled_until),updated_at=UTC_TIMESTAMP() WHERE email=?").bind(if attempts>=5 {0} else {attempts}).bind(attempts).bind(&user.email).execute(&mut *tx).await?;
        tx.commit().await?;
        return Err(Error::invalid(
            "Your username/email and password combination was incorrect.",
        ));
    }
    if user.confirm_token != "1" {
        return Err(Error::invalid(
            "Please confirm your email first. You can resend the confirmation below.",
        ));
    }
    sqlx::query("UPDATE users SET failed_attempts=0,updated_at=UTC_TIMESTAMP() WHERE email=?")
        .bind(&user.email)
        .execute(&mut *tx)
        .await?;
    tx.commit().await?;
    rotate(
        a,
        session,
        &user.email,
        f.contains_key("remember"),
        Some(&user.password),
    )
    .await
}
async fn join(
    state: &AppState,
    a: &Accounts,
    session: &Session,
    f: &BTreeMap<String, String>,
) -> Result<Response, Error> {
    if session.member() {
        return Ok(Redirect::to("/account").into_response());
    }
    let username = value(f, "username");
    let email = value(f, "email");
    if !(4..=128).contains(&username.len()) || !username.bytes().all(|c| c.is_ascii_alphanumeric())
    {
        return Err(Error::invalid(
            "Username must contain 4–128 letters or numbers.",
        ));
    }
    if !(4..=128).contains(&email.len()) || email.parse::<lettre::Address>().is_err() {
        return Err(Error::invalid(
            "Enter a valid email address with at most 128 characters.",
        ));
    }
    let password = valid_password(f)?;
    a.captcha(state, value(f, "g-recaptcha-response")).await?;
    let hash = hash_password(password).await?;
    let token = random_token();
    let mut tx = a.pool.begin().await?;
    // A named lock serializes username uniqueness on legacy schemas without an index.
    let lock_name = format!(
        "rust-user-{}",
        &digest(&username.to_ascii_lowercase())[..40]
    );
    let mut conn = a.pool.acquire().await?;
    conn.close_on_drop();
    let locked: Option<i64> = sqlx::query_scalar("SELECT GET_LOCK(?,5)")
        .bind(&lock_name)
        .fetch_one(&mut *conn)
        .await?;
    if locked != Some(1) {
        return Err(Error::internal());
    }
    let result=async {
        let exists:i64=sqlx::query_scalar("SELECT COUNT(*) FROM users WHERE email=? OR username=?").bind(email).bind(username).fetch_one(&mut *tx).await?;
        if exists>0 {return Err(Error::invalid("That username or email is already in use."));}
        let guest=!session.email.is_empty() && session.role=="Guest";
        sqlx::query("INSERT INTO users(email,username,password,role,converted_guest,remember_token,disabled_until,failed_attempts,confirm_token,created_at,updated_at) VALUES(?,?,?,'Standard',?,'',UTC_TIMESTAMP(),0,'pending',UTC_TIMESTAMP(),UTC_TIMESTAMP())").bind(email).bind(username).bind(hash).bind(i64::from(guest)).execute(&mut *tx).await?;
        if guest {
            sqlx::query("UPDATE loadout_user SET user_id=? WHERE user_id=?").bind(email).bind(&session.email).execute(&mut *tx).await?;
            sqlx::query("UPDATE loadouts SET user_id=? WHERE user_id=?").bind(email).bind(&session.email).execute(&mut *tx).await?;
            sqlx::query("UPDATE rust_sessions SET user_email=NULL WHERE user_email=?").bind(&session.email).execute(&mut *tx).await?;
            sqlx::query("DELETE FROM users WHERE email=? AND role='Guest'").bind(&session.email).execute(&mut *tx).await?;
        }
        insert_token(&mut tx,email,"confirm",&token,24*3600).await?;
        a.email(email,"Confirm your Game Loadouts email",&format!("{}/user/confirm/{token}",a.account_url)).await?;
        tx.commit().await?;Ok(web::account_page(state,session,"info",200,"Your account was created. Please check your email to confirm it.",""))
    }.await;
    // Closing the connection also releases the lock if this future is cancelled.
    sqlx::query("SELECT RELEASE_LOCK(?)")
        .bind(&lock_name)
        .execute(&mut *conn)
        .await?;
    result
}
async fn insert_token(
    tx: &mut Transaction<'_, MySql>,
    email: &str,
    purpose: &str,
    token: &str,
    ttl: i64,
) -> Result<(), Error> {
    sqlx::query("INSERT INTO rust_account_tokens(user_email,purpose,token_hash,created_at,expires_at) VALUES(?,?,?,UTC_TIMESTAMP(),TIMESTAMPADD(SECOND,?,UTC_TIMESTAMP())) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),created_at=VALUES(created_at),expires_at=VALUES(expires_at)").bind(email).bind(purpose).bind(digest(token)).bind(ttl).execute(&mut **tx).await?;
    Ok(())
}
async fn send_token(
    a: &Accounts,
    session: &Session,
    f: &BTreeMap<String, String>,
    purpose: &str,
    state: &AppState,
) -> Result<Response, Error> {
    let email = value(f, "email");
    a.captcha(state, value(f, "g-recaptcha-response")).await?;
    let mut tx = a.pool.begin().await?;
    let found:Option<String>=sqlx::query_scalar("SELECT email FROM users WHERE email=? AND role<>'Guest' AND (?='reset' OR confirm_token<>'1') FOR UPDATE").bind(email).bind(purpose).fetch_optional(&mut *tx).await?;
    if let Some(email) = found {
        let recent:i64=sqlx::query_scalar("SELECT COUNT(*) FROM rust_account_tokens WHERE user_email=? AND created_at>TIMESTAMPADD(SECOND,-60,UTC_TIMESTAMP())").bind(&email).fetch_one(&mut *tx).await?;
        if recent == 0 {
            let token = random_token();
            insert_token(
                &mut tx,
                &email,
                purpose,
                &token,
                if purpose == "reset" { 3600 } else { 24 * 3600 },
            )
            .await?;
            let route = if purpose == "reset" {
                "password/reset"
            } else {
                "user/confirm"
            };
            a.email(
                &email,
                if purpose == "reset" {
                    "Reset your Game Loadouts password"
                } else {
                    "Confirm your Game Loadouts email"
                },
                &format!("{}/{route}/{token}", a.account_url),
            )
            .await?;
        }
    }
    tx.commit().await?;
    Ok(web::account_page(
        state,
        session,
        "info",
        200,
        "If the account is eligible, an email has been sent. Check your spam folder too.",
        "",
    ))
}
async fn reset(
    a: &Accounts,
    session: &Session,
    f: &BTreeMap<String, String>,
) -> Result<Response, Error> {
    let token = value(f, "token");
    let email = value(f, "email");
    let hash = hash_password(valid_password(f)?).await?;
    let mut tx = a.pool.begin().await?;
    let valid:Option<String>=sqlx::query_scalar("SELECT user_email FROM rust_account_tokens WHERE user_email=? AND purpose='reset' AND token_hash=? AND expires_at>UTC_TIMESTAMP() FOR UPDATE").bind(email).bind(digest(token)).fetch_optional(&mut *tx).await?;
    if valid.is_none() {
        return Err(Error::invalid(
            "The reset link is invalid or expired. Request another email.",
        ));
    }
    sqlx::query("UPDATE users SET password=?,remember_token='',failed_attempts=0,disabled_until=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE email=? AND role<>'Guest'").bind(hash).bind(email).execute(&mut *tx).await?;
    sqlx::query("DELETE FROM rust_account_tokens WHERE user_email=?")
        .bind(email)
        .execute(&mut *tx)
        .await?;
    sqlx::query("DELETE FROM rust_sessions WHERE user_email=?")
        .bind(email)
        .execute(&mut *tx)
        .await?;
    tx.commit().await?;
    // Resetting a password does not bypass email confirmation.
    let confirmed: i64 =
        sqlx::query_scalar("SELECT COUNT(*) FROM users WHERE email=? AND confirm_token='1'")
            .bind(email)
            .fetch_one(&a.pool)
            .await?;
    if confirmed == 0 {
        return Ok(Redirect::to("/login").into_response());
    }
    rotate(a, session, email, false, None).await
}
pub async fn confirm(
    State(state): State<Arc<AppState>>,
    Extension(session): Extension<Session>,
    axum::extract::Path(token): axum::extract::Path<String>,
) -> Response {
    let Some(a) = &state.accounts else {
        return StatusCode::NOT_FOUND.into_response();
    };
    let result=async {
        if token.len()<32 || token.len()>100 {return Err(Error::invalid("The confirmation link is invalid or expired."));}
        let mut tx=a.pool.begin().await?;
        let email:Option<String>=sqlx::query_scalar("SELECT user_email FROM rust_account_tokens WHERE purpose='confirm' AND token_hash=? AND expires_at>UTC_TIMESTAMP() FOR UPDATE").bind(digest(&token)).fetch_optional(&mut *tx).await?;
        let email=match email {Some(email)=>email,None=>sqlx::query_scalar("SELECT email FROM users WHERE confirm_token=? AND role<>'Guest' AND confirm_token<>'1' FOR UPDATE").bind(&token).fetch_optional(&mut *tx).await?.ok_or_else(||Error::invalid("The confirmation link is invalid or expired."))?};
        sqlx::query("UPDATE users SET confirm_token='1',updated_at=UTC_TIMESTAMP() WHERE email=?").bind(&email).execute(&mut *tx).await?;
        sqlx::query("DELETE FROM rust_account_tokens WHERE user_email=? AND purpose='confirm'").bind(&email).execute(&mut *tx).await?;tx.commit().await?;rotate(a,&session,&email,false,None).await
    }.await;
    match result {
        Ok(r) => r,
        Err(e) => web::account_page(&state, &session, "info", e.status, e.message, ""),
    }
}

/// Validate both the number of attachments and the selected slot, not just membership.
pub fn validate_attachments(
    game: &str,
    min: i64,
    max: i64,
    allowed: &[(i64, String)],
    selected: &BTreeMap<String, String>,
) -> Result<Vec<i64>, &'static str> {
    let mut ids = BTreeSet::new();
    for (slot, value) in selected {
        if matches!(slot.as_str(), "_token" | "g-recaptcha-response" | "action") || value.is_empty()
        {
            continue;
        }
        let id = value.parse::<i64>().map_err(|_| "Invalid attachment.")?;
        if !allowed.iter().any(|(a, s)| {
            *a == id
                && (s == slot
                    || (game == "Call of Duty Advanced Warfare"
                        && slot == "Other2"
                        && s == "Other1"))
        }) {
            return Err("That attachment does not belong to this weapon slot.");
        }
        if !ids.insert(id) {
            return Err("Select each attachment only once.");
        }
    }
    if (ids.len() as i64) < min || (ids.len() as i64) > max {
        return Err("Select the required number of attachments.");
    }
    Ok(ids.into_iter().collect())
}
async fn guest(a: &Accounts, session: &Session) -> Result<String, Error> {
    if !session.email.is_empty() {
        return Ok(session.email.clone());
    }
    let email = random_token();
    let hash = hash_password(random_token()).await?;
    let mut tx = a.pool.begin().await?;
    sqlx::query("INSERT INTO users(email,username,password,role,converted_guest,remember_token,disabled_until,failed_attempts,confirm_token,created_at,updated_at) VALUES(?,?,?,'Guest',0,'',UTC_TIMESTAMP(),0,'1',UTC_TIMESTAMP(),UTC_TIMESTAMP())").bind(&email).bind(&email).bind(hash).execute(&mut *tx).await?;
    let updated=sqlx::query("UPDATE rust_sessions SET user_email=?,expires_at=TIMESTAMPADD(DAY,30,UTC_TIMESTAMP()) WHERE token_hash=? AND user_email IS NULL AND expires_at>UTC_TIMESTAMP()").bind(&email).bind(&session.hash).execute(&mut *tx).await?.rows_affected();
    if updated != 1 {
        return Err(Error::invalid(
            "Your session changed. Refresh the page and try again.",
        ));
    }
    tx.commit().await?;
    Ok(email)
}
async fn add_vote(tx: &mut Transaction<'_, MySql>, id: i64, email: &str) -> Result<(), Error> {
    let exists: i64 =
        sqlx::query_scalar("SELECT COUNT(*) FROM loadout_user WHERE loadout_id=? AND user_id=?")
            .bind(id)
            .bind(email)
            .fetch_one(&mut **tx)
            .await?;
    if exists == 0 {
        sqlx::query("INSERT INTO loadout_user(loadout_id,user_id,created_at,updated_at) VALUES(?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())").bind(id).bind(email).execute(&mut **tx).await?;
    }
    Ok(())
}
pub async fn write_loadout(
    State(state): State<Arc<AppState>>,
    Extension(session): Extension<Session>,
    OriginalUri(uri): OriginalUri,
    headers: HeaderMap,
    Form(form): Form<BTreeMap<String, String>>,
) -> Response {
    let Some(a) = &state.accounts else {
        return StatusCode::NOT_FOUND.into_response();
    };
    let result=async {
        csrf(&session,&form)?;
        let parts=uri.path().trim_matches('/').split('/').map(decode).collect::<Result<Vec<_>,_>>().map_err(|_|Error::invalid("Invalid URL."))?;
        if !(2..=4).contains(&parts.len()) {return Ok(StatusCode::NOT_FOUND.into_response());}
        let is_guest=!session.member();
        let action=if parts.len()==4 {parts[3].as_str()} else {value(&form,"action")};
        if parts.len()>2 && !matches!(action,"upvote"|"upvoteGuest"|"detach") {return Ok(StatusCode::NOT_FOUND.into_response());}
        let target_id=if parts.len()>2 {Some(parts[2].parse::<i64>().map_err(|_|Error::invalid("Invalid loadout."))?)} else {None};
        let removing=action=="detach" || (is_guest && target_id.is_some_and(|id|session.votes.contains(&id)));
        if is_guest && !removing {a.captcha(&state,value(&form,"g-recaptcha-response")).await?;}
        if removing && session.email.is_empty() {return Err(Error::invalid("You have not voted for this loadout."));}
        let email=guest(a,&session).await?;
        let mut tx=a.pool.begin().await?;
        // Lock the weapon for all writes, then loadout. This serializes both set deduplication
        // and votes on legacy pivot tables which do not have a UNIQUE constraint.
        let w=sqlx::query("SELECT CAST(w.id AS SIGNED) id,CAST(w.min_attachments AS SIGNED) min_attachments,CAST(w.max_attachments AS SIGNED) max_attachments FROM weapons w JOIN games g ON g.id=w.game_id WHERE w.game_id=? AND w.name=? AND g.live=1 ORDER BY w.id LIMIT 1 FOR UPDATE").bind(&parts[0]).bind(&parts[1]).fetch_optional(&mut *tx).await?.ok_or_else(||Error::invalid("Weapon not found."))?;
        let wid:i64=w.try_get("id")?;
        let id=if let Some(id)=target_id {
            let found:Option<i64>=sqlx::query_scalar("SELECT CAST(id AS SIGNED) FROM loadouts WHERE id=? AND weapon_id=? FOR UPDATE").bind(id).bind(wid).fetch_optional(&mut *tx).await?;
            found.ok_or_else(||Error::invalid("Loadout not found for this weapon."))?
        } else {
            let allowed:Vec<(i64,String)>=sqlx::query_as("SELECT CAST(a.id AS SIGNED),a.slot FROM attachments a JOIN attachment_weapon aw ON aw.attachment_id=a.id WHERE aw.weapon_id=?").bind(wid).fetch_all(&mut *tx).await?;
            let ids=validate_attachments(&parts[0],w.try_get::<i64,_>("min_attachments")?,w.try_get::<i64,_>("max_attachments")?,&allowed,&form).map_err(Error::invalid)?;
            let mut query=sqlx::QueryBuilder::<MySql>::new("SELECT CAST(l.id AS SIGNED) FROM loadouts l LEFT JOIN attachment_loadout al ON al.loadout_id=l.id WHERE l.weapon_id=");query.push_bind(wid).push(" GROUP BY l.id HAVING COUNT(al.attachment_id)=").push_bind(ids.len() as i64);
            if !ids.is_empty() {query.push(" AND SUM(al.attachment_id IN (");let mut list=query.separated(",");for id in &ids {list.push_bind(id);}list.push_unseparated("))=");query.push_bind(ids.len() as i64);}
            query.push(" ORDER BY l.id LIMIT 1");
            let existing=query.build_query_scalar::<i64>().fetch_optional(&mut *tx).await?;
            if let Some(id)=existing {id} else {
                let id=sqlx::query("INSERT INTO loadouts(weapon_id,user_id,created_at,updated_at) VALUES(?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())").bind(wid).bind(&email).execute(&mut *tx).await?.last_insert_id() as i64;
                for aid in ids {sqlx::query("INSERT INTO attachment_loadout(attachment_id,loadout_id,created_at,updated_at) VALUES(?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())").bind(aid).bind(id).execute(&mut *tx).await?;}id
            }
        };
        if removing {sqlx::query("DELETE FROM loadout_user WHERE loadout_id=? AND user_id=?").bind(id).bind(&email).execute(&mut *tx).await?;} else {add_vote(&mut tx,id,&email).await?;}
        tx.commit().await?;
        let mut response=Redirect::to(&format!("/{}/{}/{id}",encode(&parts[0]),encode(&parts[1]))).into_response();
        if is_guest && session.email.is_empty() && let Some(token)=raw_cookie(&headers) {response.headers_mut().insert(header::SET_COOKIE,cookie(a,&token,30*24*3600));}
        Ok(response)
    }.await;
    match result {
        Ok(r) => r,
        Err(e) => web::account_page(&state, &session, "info", e.status, e.message, ""),
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn slot_validation() {
        let allowed = vec![
            (1, "Optic".into()),
            (2, "Other1".into()),
            (3, "Other1".into()),
        ];
        let form = |pairs: &[(&str, &str)]| {
            pairs
                .iter()
                .map(|(k, v)| (k.to_string(), v.to_string()))
                .collect()
        };
        assert_eq!(
            validate_attachments("x", 1, 3, &allowed, &form(&[("Optic", "1")])),
            Ok(vec![1])
        );
        assert!(validate_attachments("x", 1, 3, &allowed, &form(&[("Barrel", "1")])).is_err());
        assert!(validate_attachments("x", 1, 3, &allowed, &form(&[("Other2", "2")])).is_err());
        assert!(
            validate_attachments(
                "Call of Duty Advanced Warfare",
                1,
                3,
                &allowed,
                &form(&[("Other1", "2"), ("Other2", "2")])
            )
            .is_err()
        );
        assert_eq!(
            validate_attachments(
                "Call of Duty Advanced Warfare",
                1,
                3,
                &allowed,
                &form(&[("Other1", "2"), ("Other2", "3")])
            ),
            Ok(vec![2, 3])
        );
        assert!(validate_attachments("x", 2, 3, &allowed, &form(&[("Optic", "1")])).is_err());
        assert!(
            validate_attachments(
                "x",
                0,
                1,
                &allowed,
                &form(&[("Optic", "1"), ("Other1", "2")])
            )
            .is_err()
        );
    }
    #[test]
    fn csrf_and_cookies() {
        let session = Session {
            hash: random_token(),
            csrf: random_token(),
            ..Session::default()
        };
        assert!(csrf(&session, &BTreeMap::new()).is_err());
        let f = BTreeMap::from([("_token".into(), session.csrf.clone())]);
        assert!(csrf(&session, &f).is_ok());
        assert!(csrf(&Session::default(), &f).is_err());
        let mut headers = HeaderMap::new();
        headers.insert(
            header::COOKIE,
            HeaderValue::from_static("gameloadouts_rust_session=bad"),
        );
        assert!(raw_cookie(&headers).is_none());
        assert_ne!(digest(&random_token()), digest(&random_token()));
    }
}
