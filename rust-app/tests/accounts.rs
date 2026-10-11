//! All writes and emails stay inside a separate loopback fixture database and SMTP receiver.
use axum::{
    Router,
    body::Body,
    http::{HeaderMap, Request},
};
use game_loadouts::{
    account::Accounts,
    model::encode,
    session::{CookieSessions, now},
    web::{AppState, app},
};
use http_body_util::BodyExt;
use lettre::{AsyncSmtpTransport, Tokio1Executor};
use sqlx::{MySqlPool, mysql::MySqlPoolOptions};
use std::{collections::BTreeMap, sync::Arc, time::Duration};
use tokio::{
    io::{AsyncBufReadExt, AsyncWriteExt, BufReader},
    sync::Mutex,
};
use tower::ServiceExt;

#[derive(Clone, Default)]
struct Browser {
    cookie: String,
    csrf: String,
}
struct Reply {
    status: u16,
    headers: HeaderMap,
    body: String,
}
async fn request(
    app: &Router,
    cookie: &str,
    path: &str,
    method: &str,
    fields: &[(&str, &str)],
) -> Reply {
    let body = fields
        .iter()
        .map(|(k, v)| format!("{}={}", encode(k), encode(v)))
        .collect::<Vec<_>>()
        .join("&");
    let r = app
        .clone()
        .oneshot(
            Request::builder()
                .uri(path)
                .method(method)
                .header("cookie", cookie)
                .header("content-type", "application/x-www-form-urlencoded")
                .body(Body::from(body))
                .unwrap(),
        )
        .await
        .unwrap();
    let status = r.status().as_u16();
    let headers = r.headers().clone();
    let body =
        String::from_utf8(r.into_body().collect().await.unwrap().to_bytes().to_vec()).unwrap();
    Reply {
        status,
        headers,
        body,
    }
}
impl Browser {
    fn accept(&mut self, r: &Reply) {
        if let Some(c) = r.headers.get("set-cookie") {
            self.cookie = c.to_str().unwrap().split(';').next().unwrap().into();
        }
        if let Some((_, rest)) = r.body.split_once("name=\"_token\" value=\"") {
            self.csrf = rest.split('"').next().unwrap().into();
        }
    }
    async fn get(&mut self, app: &Router, path: &str) -> Reply {
        let r = request(app, &self.cookie, path, "GET", &[]).await;
        self.accept(&r);
        r
    }
    async fn post(&mut self, app: &Router, path: &str, fields: &[(&str, &str)]) -> Reply {
        let token = self.csrf.clone();
        let mut f = fields.to_vec();
        f.push(("_token", &token));
        let r = request(app, &self.cookie, path, "POST", &f).await;
        self.accept(&r);
        r
    }
}
async fn smtp() -> (u16, Arc<Mutex<Vec<String>>>, tokio::task::JoinHandle<()>) {
    let listener = tokio::net::TcpListener::bind("127.0.0.1:0").await.unwrap();
    let port = listener.local_addr().unwrap().port();
    let messages = Arc::new(Mutex::new(Vec::new()));
    let captured = messages.clone();
    let task = tokio::spawn(async move {
        while let Ok((stream, _)) = listener.accept().await {
            let messages = captured.clone();
            tokio::spawn(async move {
                let (r, mut w) = stream.into_split();
                let mut r = BufReader::new(r);
                w.write_all(b"220 fixture SMTP\r\n").await.unwrap();
                loop {
                    let mut line = String::new();
                    if r.read_line(&mut line).await.unwrap() == 0 {
                        break;
                    }
                    if line.starts_with("EHLO") {
                        w.write_all(b"250 fixture\r\n").await.unwrap();
                    } else if line.starts_with("RCPT TO:")
                        && line.contains("mail-failure@example.invalid")
                    {
                        w.write_all(b"550 fixture rejects recipient\r\n")
                            .await
                            .unwrap();
                    } else if line.starts_with("DATA") {
                        w.write_all(b"354 send mail\r\n").await.unwrap();
                        let mut mail = String::new();
                        loop {
                            let mut line = String::new();
                            if r.read_line(&mut line).await.unwrap() == 0 || line == ".\r\n" {
                                break;
                            }
                            mail.push_str(&line);
                        }
                        messages.lock().await.push(mail);
                        w.write_all(b"250 accepted\r\n").await.unwrap();
                    } else if line.starts_with("QUIT") {
                        w.write_all(b"221 bye\r\n").await.unwrap();
                        break;
                    } else {
                        w.write_all(b"250 ok\r\n").await.unwrap();
                    }
                }
            });
        }
    });
    (port, messages, task)
}
async fn captcha() -> (String, tokio::task::JoinHandle<()>) {
    let listener = tokio::net::TcpListener::bind("127.0.0.1:0").await.unwrap();
    let port = listener.local_addr().unwrap().port();
    let router=Router::new().route("/verify",axum::routing::post(|axum::extract::Form(f):axum::extract::Form<BTreeMap<String,String>>| async move {let response=f.get("response").map(String::as_str).unwrap_or("");axum::Json(serde_json::json!({"success":matches!(response,"fixture-captcha"|"wrong-host"),"hostname":if response=="wrong-host" {"other.invalid"} else {"fixture.invalid"}}))}));
    let task = tokio::spawn(async move {
        axum::serve(listener, router).await.unwrap();
    });
    (format!("http://127.0.0.1:{port}/verify"), task)
}
async fn fixture() -> (
    Router,
    MySqlPool,
    MySqlPool,
    Arc<Mutex<Vec<String>>>,
    Vec<tokio::task::JoinHandle<()>>,
) {
    let url = std::env::var("TEST_DATABASE_URL").expect("isolated fixture URL required");
    assert!(
        url.ends_with("/loadouts_fixture")
            && (url.contains("@127.0.0.1:") || url.contains("@localhost:"))
    );
    let owner = MySqlPoolOptions::new().connect(&url).await.unwrap();
    sqlx::query("CREATE DATABASE loadouts_accounts_fixture")
        .execute(&owner)
        .await
        .unwrap();
    let schema = include_str!("../fixtures/schema.sql")
        .replace("loadouts_fixture", "loadouts_accounts_fixture")
        .replace(
            ") ENGINE=InnoDB CHARACTER SET utf8mb4;",
            ") ENGINE=MyISAM CHARACTER SET utf8;",
        )
        .lines()
        .filter(|l| !l.starts_with("CREATE USER "))
        .collect::<Vec<_>>()
        .join("\n");
    sqlx::raw_sql(&schema).execute(&owner).await.unwrap();
    let pool = MySqlPoolOptions::new()
        .max_connections(12)
        .connect(&url.replace("/loadouts_fixture", "/loadouts_accounts_fixture"))
        .await
        .unwrap();
    sqlx::raw_sql(
        &include_str!("../fixtures/data.sql")
            .replace("loadouts_fixture", "loadouts_accounts_fixture"),
    )
    .execute(&pool)
    .await
    .unwrap();
    let hash = bcrypt::hash("legacy-password", 4)
        .unwrap()
        .replace("$2b$", "$2y$");
    sqlx::query("INSERT INTO users(email,username,role,password,confirm_token) VALUES('tester@example.invalid','SecurityTester','Standard',?,'1')").bind(hash).execute(&pool).await.unwrap();
    let (port, messages, mail_task) = smtp().await;
    let (captcha_url, captcha_task) = captcha().await;
    let read = MySqlPoolOptions::new()
        .max_connections(4)
        .after_connect(|c, _| {
            Box::pin(async move {
                sqlx::query("SET SESSION TRANSACTION READ ONLY")
                    .execute(c)
                    .await?;
                Ok(())
            })
        })
        .connect(
            &url.replace("root@", "loadouts_reader@")
                .replace("/loadouts_fixture", "/loadouts_accounts_fixture"),
        )
        .await
        .unwrap();
    let state = Arc::new(AppState {
        ads: false,
        analytics_id: String::new(),
        accounts: Some(Arc::new(Accounts {
            pool: pool.clone(),
            sessions: fixture_sessions(),
            secure_cookie: true,
            site_key: "fixture-key".into(),
            captcha_secret: "fixture-secret".into(),
            captcha_url,
            captcha_hostname: "fixture.invalid".into(),
            disqus_secret: "fixture-disqus-secret".into(),
            mailer: Some(
                AsyncSmtpTransport::<Tokio1Executor>::builder_dangerous("127.0.0.1")
                    .port(port)
                    .build(),
            ),
            mail_from: "Game Loadouts <mail@example.invalid>".parse().unwrap(),
            account_url: "https://fixture.invalid".into(),
        })),
        pool: read,
        query_timeout: Duration::from_secs(5),
        public_url: "https://gameloadouts.com".into(),
        media_origin: String::new(),
        disqus: true,
        metrics: false,
        search_mode: "local".into(),
        search_api_url: String::new(),
        http: reqwest::Client::builder()
            .timeout(Duration::from_secs(5))
            .build()
            .unwrap(),
    });
    (
        app(
            state,
            std::path::Path::new(env!("CARGO_MANIFEST_DIR"))
                .parent()
                .unwrap()
                .join("public")
                .to_str()
                .unwrap(),
        ),
        pool,
        owner,
        messages,
        vec![mail_task, captcha_task],
    )
}
fn fixture_sessions() -> CookieSessions {
    CookieSessions::new(
        b"synthetic-session-key-32-bytes-long",
        "https://fixture.invalid",
    )
    .unwrap()
}
fn mail_token(mail: &str, route: &str) -> String {
    mail.replace("=\r\n", "")
        .split(&format!("https://fixture.invalid/{route}/"))
        .nth(1)
        .unwrap()
        .chars()
        .take_while(|c| c.is_ascii_alphanumeric())
        .collect()
}

#[tokio::test]
#[ignore = "requires an explicitly isolated loopback MySQL fixture"]
async fn public_write_flows_and_security() {
    let _ = tracing_subscriber::fmt()
        .with_test_writer()
        .with_max_level(tracing::Level::ERROR)
        .try_init();
    let (app, pool, owner, messages, tasks) = fixture().await;
    game_loadouts::schema::validate(&pool).await.unwrap();
    let reminder_engine:String=sqlx::query_scalar("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='password_reminders'").fetch_one(&pool).await.unwrap();
    assert_eq!(
        reminder_engine, "MyISAM",
        "Exercise the legacy reminder engine without a production migration"
    );
    let extra_tables:i64=sqlx::query_scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('rust_sessions','rust_account_tokens')").fetch_one(&pool).await.unwrap();
    assert_eq!(
        extra_tables, 0,
        "The account flows must work with only the existing Laravel schema"
    );
    let mut member = Browser::default();
    let login = member.get(&app, "/login").await;
    assert_eq!(login.status, 200);
    assert_eq!(member.csrf.len(), 64);
    let cookie = login.headers["set-cookie"].to_str().unwrap();
    assert!(
        cookie.contains("HttpOnly") && cookie.contains("Secure") && cookie.contains("SameSite=Lax")
    );
    let anonymous = member.clone();
    assert_eq!(
        request(
            &app,
            &member.cookie,
            "/login",
            "POST",
            &[
                ("emailOrUsername", "SecurityTester"),
                ("password", "legacy-password")
            ]
        )
        .await
        .status,
        403
    );
    assert_eq!(
        member
            .post(
                &app,
                "/login",
                &[
                    ("emailOrUsername", "SecurityTester"),
                    ("password", "legacy-password"),
                    ("remember", "1")
                ]
            )
            .await
            .status,
        303
    );
    assert_ne!(member.cookie, anonymous.cookie);
    let account = member.get(&app, "/account").await;
    assert_eq!(account.status, 200);
    assert!(account.body.contains("SecurityTester"));
    assert_eq!(
        request(&app, &anonymous.cookie, "/account", "GET", &[])
            .await
            .status,
        303
    );
    let raw = member.cookie.split('=').nth(1).unwrap();
    let sessions = fixture_sessions();
    let claims = sessions.open(raw).unwrap();
    assert_eq!(claims.email, "tester@example.invalid");
    assert!(claims.expires_at > now() + 29 * 24 * 3600);
    assert!(raw.len() < 2048 && !raw.contains("tester"));
    let mut expired = claims;
    expired.expires_at = now() - 1;
    let bad_cookie = format!(
        "gameloadouts_rust_session={}",
        sessions.seal(&expired).unwrap()
    );
    assert_eq!(
        request(&app, &bad_cookie, "/account", "GET", &[])
            .await
            .status,
        303
    );
    let mut tampered = raw.as_bytes().to_vec();
    tampered[30] = if tampered[30] == b'a' { b'b' } else { b'a' };
    let bad_cookie = format!(
        "gameloadouts_rust_session={}",
        String::from_utf8(tampered).unwrap()
    );
    assert_eq!(
        request(&app, &bad_cookie, "/account", "GET", &[])
            .await
            .status,
        303
    );
    assert_eq!(
        request(
            &app,
            &bad_cookie,
            "/login",
            "POST",
            &[("_token", member.csrf.as_str())]
        )
        .await
        .status,
        403
    );
    let weapon = "/Call+of+Duty+Modern+Warfare/M4A1";
    let loadout = format!("{weapon}/1");
    assert_eq!(
        request(
            &app,
            &member.cookie,
            &format!("{loadout}/upvote"),
            "POST",
            &[("_token", "bad")]
        )
        .await
        .status,
        403
    );
    let fields = [("_token", member.csrf.as_str())];
    let vote_path = format!("{loadout}/upvote");
    let (r1, r2) = tokio::join!(
        request(&app, &member.cookie, &vote_path, "POST", &fields),
        request(&app, &member.cookie, &vote_path, "POST", &fields)
    );
    assert_eq!((r1.status, r2.status), (303, 303));
    let votes: i64 = sqlx::query_scalar(
        "SELECT COUNT(*) FROM loadout_user WHERE loadout_id=1 AND user_id='tester@example.invalid'",
    )
    .fetch_one(&pool)
    .await
    .unwrap();
    assert_eq!(votes, 1);
    assert_eq!(
        member
            .post(&app, "/Apex+Legends/R-301/1/upvote", &[])
            .await
            .status,
        422
    );
    assert_eq!(
        member
            .post(&app, &format!("{loadout}/detach"), &[])
            .await
            .status,
        303
    );
    assert_eq!(
        member
            .post(&app, &format!("{loadout}/detach"), &[])
            .await
            .status,
        303
    );
    let other_votes: i64 =
        sqlx::query_scalar("SELECT COUNT(*) FROM loadout_user WHERE loadout_id=1")
            .fetch_one(&pool)
            .await
            .unwrap();
    assert_eq!(other_votes, 3);
    assert_eq!(
        member.post(&app, weapon, &[("Barrel", "1")]).await.status,
        422
    );
    assert_eq!(
        member
            .post(&app, weapon, &[("Optic", "999999")])
            .await
            .status,
        422
    );
    assert_eq!(member.post(&app, weapon, &[]).await.status, 422);
    let fields = [
        ("_token", member.csrf.as_str()),
        ("Optic", "1"),
        ("Underbarrel", "2"),
    ];
    let (r1, r2) = tokio::join!(
        request(&app, &member.cookie, weapon, "POST", &fields),
        request(&app, &member.cookie, weapon, "POST", &fields)
    );
    assert_eq!((r1.status, r2.status), (303, 303));
    assert_eq!(r1.headers["location"], r2.headers["location"]);
    let new_id = r1.headers["location"]
        .to_str()
        .unwrap()
        .rsplit('/')
        .next()
        .unwrap()
        .parse::<i64>()
        .unwrap();
    let new_count: i64 = sqlx::query_scalar("SELECT COUNT(*) FROM loadouts WHERE id>50")
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(new_count, 1);
    let count: i64 = sqlx::query_scalar("SELECT COUNT(*) FROM loadout_user WHERE loadout_id=?")
        .bind(new_id)
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(count, 1);
    // Legacy pivots allow duplicate rows; the same attachment set must still reuse the loadout.
    sqlx::query("INSERT INTO attachment_loadout (attachment_id,loadout_id) VALUES (1,?)")
        .bind(new_id)
        .execute(&pool)
        .await
        .unwrap();
    let repeated = member
        .post(&app, weapon, &[("Optic", "1"), ("Underbarrel", "2")])
        .await;
    assert_eq!(repeated.status, 303);
    assert_eq!(repeated.headers["location"], r1.headers["location"]);
    let new_count: i64 = sqlx::query_scalar("SELECT COUNT(*) FROM loadouts WHERE id>50")
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(new_count, 1);
    let submissions = member.get(&app, "/submissions").await;
    assert_eq!(submissions.status, 200);
    assert!(submissions.body.contains(&format!("/{new_id}")));
    // Guest additions require CAPTCHA, removals do not, and registration preserves history.
    let mut guest = Browser::default();
    guest.get(&app, &loadout).await;
    assert_eq!(
        guest
            .post(&app, &format!("{loadout}/upvoteGuest"), &[])
            .await
            .status,
        422
    );
    assert_eq!(
        guest
            .post(
                &app,
                &format!("{loadout}/upvoteGuest"),
                &[("g-recaptcha-response", "wrong-host")]
            )
            .await
            .status,
        422
    );
    assert_eq!(
        guest
            .post(
                &app,
                &format!("{loadout}/upvoteGuest"),
                &[("g-recaptcha-response", "fixture-captcha")]
            )
            .await
            .status,
        303
    );
    guest.get(&app, &loadout).await;
    assert_eq!(
        guest
            .post(&app, &format!("{loadout}/detach"), &[])
            .await
            .status,
        303
    );
    guest.get(&app, &loadout).await;
    assert_eq!(
        guest
            .post(
                &app,
                &format!("{loadout}/upvoteGuest"),
                &[("g-recaptcha-response", "fixture-captcha")]
            )
            .await
            .status,
        303
    );
    let old_email = fixture_sessions()
        .open(guest.cookie.split_once('=').unwrap().1)
        .unwrap()
        .email;
    guest.get(&app, "/join").await;
    assert_eq!(
        guest
            .post(
                &app,
                "/join",
                &[
                    ("username", "NewMember"),
                    ("email", "new@example.invalid"),
                    ("password", "new-password"),
                    ("password_confirmation", "new-password"),
                    ("g-recaptcha-response", "fixture-captcha")
                ]
            )
            .await
            .status,
        200
    );
    let converted: i64 = sqlx::query_scalar(
        "SELECT CAST(converted_guest AS SIGNED) FROM users WHERE email='new@example.invalid'",
    )
    .fetch_one(&pool)
    .await
    .unwrap();
    assert_eq!(converted, 1);
    let moved: i64 = sqlx::query_scalar(
        "SELECT COUNT(*) FROM loadout_user WHERE loadout_id=1 AND user_id='new@example.invalid'",
    )
    .fetch_one(&pool)
    .await
    .unwrap();
    assert_eq!(moved, 1);
    let stale: i64 = sqlx::query_scalar("SELECT COUNT(*) FROM users WHERE email=?")
        .bind(old_email)
        .fetch_one(&pool)
        .await
        .unwrap();
    assert_eq!(stale, 0);
    let token = mail_token(&messages.lock().await[0], "user/confirm");
    assert_eq!(token.len(), 82);
    let stored_confirmation: String =
        sqlx::query_scalar("SELECT confirm_token FROM users WHERE email='new@example.invalid'")
            .fetch_one(&pool)
            .await
            .unwrap();
    assert_eq!(stored_confirmation, token);
    // Registration rotated the anonymous cookie; load the login form for its new CSRF token.
    guest.get(&app, "/login").await;
    assert_eq!(
        guest
            .post(
                &app,
                "/login",
                &[
                    ("emailOrUsername", "new@example.invalid"),
                    ("password", "new-password")
                ]
            )
            .await
            .status,
        422
    );
    assert_eq!(
        guest
            .get(&app, &format!("/user/confirm/{token}"))
            .await
            .status,
        303
    );
    guest.get(&app, "/account").await;
    assert_eq!(
        guest
            .get(&app, &format!("/user/confirm/{token}"))
            .await
            .status,
        422
    );
    // Reset tokens expire, are email-bound and single-use; resetting revokes old sessions.
    let old_member = guest.clone();
    let mut resetter = Browser::default();
    resetter.get(&app, "/reminder").await;
    assert_eq!(
        resetter
            .post(
                &app,
                "/reminder",
                &[
                    ("email", "new@example.invalid"),
                    ("g-recaptcha-response", "fixture-captcha")
                ]
            )
            .await
            .status,
        200
    );
    let reset_token = mail_token(messages.lock().await.last().unwrap(), "password/reset");
    assert_eq!(reset_token.len(), 64);
    let stored_reset: String = sqlx::query_scalar(
        "SELECT token FROM password_reminders WHERE email='new@example.invalid'",
    )
    .fetch_one(&pool)
    .await
    .unwrap();
    assert_eq!(
        stored_reset, reset_token,
        "Laravel 4.1 must be able to consume Rust-issued reset tokens"
    );
    resetter
        .get(&app, &format!("/password/reset/{reset_token}"))
        .await;
    let fields = [
        ("email", "new@example.invalid"),
        ("token", reset_token.as_str()),
        ("password", "reset-password"),
        ("password_confirmation", "reset-password"),
    ];
    let wrong_email = [
        ("email", "tester@example.invalid"),
        ("token", reset_token.as_str()),
        ("password", "reset-password"),
        ("password_confirmation", "reset-password"),
    ];
    assert_eq!(
        resetter
            .post(&app, "/password/reset", &wrong_email)
            .await
            .status,
        422
    );
    sqlx::query("UPDATE password_reminders SET created_at=TIMESTAMPADD(MINUTE,-61,UTC_TIMESTAMP()) WHERE email='new@example.invalid'").execute(&pool).await.unwrap();
    assert_eq!(
        resetter.post(&app, "/password/reset", &fields).await.status,
        422
    );
    sqlx::query("UPDATE password_reminders SET created_at=UTC_TIMESTAMP() WHERE email='new@example.invalid'").execute(&pool).await.unwrap();
    assert_eq!(
        resetter.post(&app, "/password/reset", &fields).await.status,
        303
    );
    assert_eq!(
        request(&app, &old_member.cookie, "/account", "GET", &[])
            .await
            .status,
        303
    );
    resetter.get(&app, "/account").await;
    assert_eq!(
        resetter.post(&app, "/password/reset", &fields).await.status,
        422
    );
    assert_eq!(
        resetter
            .post(
                &app,
                "/account",
                &[
                    ("current_password", "wrong"),
                    ("password", "changed-password"),
                    ("password_confirmation", "changed-password")
                ]
            )
            .await
            .status,
        422
    );
    let before_change = resetter.clone();
    assert_eq!(
        resetter
            .post(
                &app,
                "/account",
                &[
                    ("current_password", "reset-password"),
                    ("password", "changed-password"),
                    ("password_confirmation", "changed-password")
                ]
            )
            .await
            .status,
        303
    );
    assert_eq!(
        request(&app, &before_change.cookie, "/account", "GET", &[])
            .await
            .status,
        303
    );
    resetter.get(&app, "/account").await;
    let before_logout = resetter.clone();
    assert_eq!(resetter.get(&app, "/logout").await.status, 200);
    assert_eq!(resetter.get(&app, "/account").await.status, 200);
    assert_eq!(resetter.post(&app, "/logout", &[]).await.status, 303);
    assert_eq!(
        request(&app, &before_logout.cookie, "/account", "GET", &[])
            .await
            .status,
        303
    );
    // Legacy email confirmation links remain valid and the sentinel '1' cannot log in anyone.
    let legacy_token = "a".repeat(100);
    sqlx::query("INSERT INTO users (email,username,password,role,confirm_token) VALUES ('legacy-confirm@example.invalid','LegacyConfirm','fixture-hash','Standard',?)")
        .bind(&legacy_token).execute(&pool).await.unwrap();
    let legacy_path = format!("/user/confirm/{legacy_token}");
    let mut legacy = Browser::default();
    assert_eq!(legacy.get(&app, &legacy_path).await.status, 303);
    assert_eq!(legacy.get(&app, &legacy_path).await.status, 422);
    assert_eq!(member.get(&app, "/user/confirm/1").await.status, 422);
    let mut locked = Browser::default();
    locked.get(&app, "/login").await;
    for _ in 0..5 {
        assert_eq!(
            locked
                .post(
                    &app,
                    "/login",
                    &[("emailOrUsername", "SecurityTester"), ("password", "wrong")]
                )
                .await
                .status,
            422
        );
    }
    assert_eq!(
        locked
            .post(
                &app,
                "/login",
                &[
                    ("emailOrUsername", "SecurityTester"),
                    ("password", "legacy-password")
                ]
            )
            .await
            .status,
        422
    );
    // A password update performed by Laravel also invalidates Rust sessions.
    sqlx::query(
        "UPDATE users SET password='changed-by-legacy' WHERE email='tester@example.invalid'",
    )
    .execute(&pool)
    .await
    .unwrap();
    assert_eq!(member.get(&app, "/account").await.status, 303);
    // SMTP failure preserves an existing raw reminder row even on nontransactional MyISAM.
    sqlx::query("INSERT INTO users(email,username,password,role,confirm_token) VALUES('mail-failure@example.invalid','MailFailure','fixture-hash','Standard','1')").execute(&pool).await.unwrap();
    let previous_reminder = "d".repeat(40);
    sqlx::query("INSERT INTO password_reminders(email,token,created_at) VALUES('mail-failure@example.invalid',?,TIMESTAMPADD(MINUTE,-2,UTC_TIMESTAMP()))").bind(&previous_reminder).execute(&pool).await.unwrap();
    let mut mail_failure = Browser::default();
    mail_failure.get(&app, "/reminder").await;
    assert_eq!(
        mail_failure
            .post(
                &app,
                "/reminder",
                &[
                    ("email", "mail-failure@example.invalid"),
                    ("g-recaptcha-response", "fixture-captcha")
                ]
            )
            .await
            .status,
        422
    );
    assert_eq!(
        sqlx::query_scalar::<_, String>(
            "SELECT token FROM password_reminders WHERE email='mail-failure@example.invalid'"
        )
        .fetch_one(&pool)
        .await
        .unwrap(),
        previous_reminder
    );
    // Laravel-issued reminder rows (40-character SHA1 tokens) work on Rust, with no new tables.
    let legacy_reset = "b".repeat(40);
    sqlx::query("INSERT INTO password_reminders(email,token,created_at) VALUES('tester@example.invalid',?,UTC_TIMESTAMP())").bind(&legacy_reset).execute(&pool).await.unwrap();
    let mut legacy_reset_browser = Browser::default();
    legacy_reset_browser
        .get(&app, &format!("/password/reset/{legacy_reset}"))
        .await;
    assert_eq!(
        legacy_reset_browser
            .post(
                &app,
                "/password/reset",
                &[
                    ("email", "tester@example.invalid"),
                    ("token", &legacy_reset),
                    ("password", "legacy-reset-password"),
                    ("password_confirmation", "legacy-reset-password")
                ]
            )
            .await
            .status,
        303
    );
    assert_eq!(
        sqlx::query_scalar::<_, i64>(
            "SELECT COUNT(*) FROM password_reminders WHERE email='tester@example.invalid'"
        )
        .fetch_one(&pool)
        .await
        .unwrap(),
        0
    );
    // New confirmation links expire without adding columns; old Laravel links remain accepted.
    let expired_confirmation = format!("r1{:016x}{}", now() - 24 * 3600 - 1, "c".repeat(64));
    sqlx::query("INSERT INTO users(email,username,password,role,confirm_token) VALUES('expired@example.invalid','ExpiredConfirm','fixture-hash','Standard',?)").bind(&expired_confirmation).execute(&pool).await.unwrap();
    assert_eq!(
        legacy
            .get(&app, &format!("/user/confirm/{expired_confirmation}"))
            .await
            .status,
        422
    );
    // Simultaneous first-time guest votes derive a single shared identity from the encrypted cookie.
    let mut parallel_guest = Browser::default();
    parallel_guest.get(&app, &loadout).await;
    let fields = [
        ("_token", parallel_guest.csrf.as_str()),
        ("g-recaptcha-response", "fixture-captcha"),
    ];
    let parallel_vote_path = format!("{loadout}/upvoteGuest");
    let (left, right) = tokio::join!(
        request(
            &app,
            &parallel_guest.cookie,
            &parallel_vote_path,
            "POST",
            &fields
        ),
        request(
            &app,
            &parallel_guest.cookie,
            &parallel_vote_path,
            "POST",
            &fields
        )
    );
    assert_eq!(left.status, 303);
    assert_eq!(right.status, 303);
    let left_cookie = left.headers["set-cookie"]
        .to_str()
        .unwrap()
        .split(';')
        .next()
        .unwrap()
        .split_once('=')
        .unwrap()
        .1;
    let right_cookie = right.headers["set-cookie"]
        .to_str()
        .unwrap()
        .split(';')
        .next()
        .unwrap()
        .split_once('=')
        .unwrap()
        .1;
    let left_email = fixture_sessions().open(left_cookie).unwrap().email;
    assert_eq!(
        left_email,
        fixture_sessions().open(right_cookie).unwrap().email
    );
    assert_eq!(
        sqlx::query_scalar::<_, i64>(
            "SELECT COUNT(*) FROM loadout_user WHERE user_id=? AND loadout_id=1"
        )
        .bind(left_email)
        .fetch_one(&pool)
        .await
        .unwrap(),
        1
    );
    // Exercise the rendered forms in Chromium against this isolated service.
    if std::env::var("RUN_BROWSER_TESTS").as_deref() == Ok("1") {
        let listener = tokio::net::TcpListener::bind("127.0.0.1:0").await.unwrap();
        let port = listener.local_addr().unwrap().port();
        let server_app = app.clone();
        let server = tokio::spawn(async move {
            axum::serve(listener, server_app).await.unwrap();
        });
        let script =
            std::path::Path::new(env!("CARGO_MANIFEST_DIR")).join("tools/browser_accounts.mjs");
        let status = tokio::task::spawn_blocking(move || {
            std::process::Command::new("node")
                .arg(script)
                .env("BASE_URL", format!("http://127.0.0.1:{port}"))
                .env(
                    "SCREENSHOT_DIR",
                    std::path::Path::new(env!("CARGO_MANIFEST_DIR"))
                        .parent()
                        .unwrap()
                        .join("browser-screenshots/accounts"),
                )
                .status()
                .unwrap()
        })
        .await
        .unwrap();
        server.abort();
        assert!(status.success(), "interactive browser smoke failed");
    }
    // Public and admin boundaries.
    assert_eq!(member.get(&app, "/admin").await.status, 404);
    assert_eq!(member.get(&app, "/robots.txt").await.status, 200);
    for task in tasks {
        task.abort();
    }
    pool.close().await;
    sqlx::query("DROP DATABASE loadouts_accounts_fixture")
        .execute(&owner)
        .await
        .unwrap();
    owner.close().await;
}
