//! Existing administration, confined to the Rust database and current Admin sessions.
use crate::{
    account::Session,
    model::{decode, encode},
    remote::{self as sqlx, Connection, Pool, WriteTransaction},
    web::AppState,
};
use askama::Template;
use axum::{
    Extension, Router,
    body::to_bytes,
    extract::{DefaultBodyLimit, FromRequest, Multipart, OriginalUri, Request, State},
    http::{HeaderValue, StatusCode, header},
    response::{Html, IntoResponse, Redirect, Response},
    routing::any,
};
use libsql::Value as SqlValue;
use serde::Deserialize;
use serde_json::Value;
use std::{
    collections::{BTreeMap, BTreeSet},
    sync::Arc,
};
#[derive(Clone, Debug, Default, Deserialize)]
pub struct Record {
    #[serde(flatten)]
    pub fields: BTreeMap<String, Value>,
    #[serde(default)]
    pub attachments: Vec<Record>,
}
impl Record {
    pub fn field(&self, name: &str) -> String {
        match self.fields.get(name) {
            Some(Value::String(v)) => v.clone(),
            Some(Value::Number(v)) => v.to_string(),
            Some(Value::Bool(v)) => i32::from(*v).to_string(),
            _ => String::new(),
        }
    }
    fn number(&self, name: &str) -> i64 {
        self.fields.get(name).and_then(Value::as_i64).unwrap_or(0)
    }
    fn text(&mut self, name: &str, value: impl Into<String>) {
        self.fields.insert(name.into(), Value::String(value.into()));
    }
    pub fn date(&self, name: &str) -> String {
        self.field(name)
            .replace('T', " ")
            .chars()
            .take(19)
            .collect()
    }
    pub fn truncate(&self, name: &str, length: usize) -> String {
        self.field(name).chars().take(length).collect()
    }
    pub fn weapon(&self) -> Record {
        let mut r = Record::default();
        r.text("id", self.field("weapon_id"));
        r.text("name", self.field("weapon_name"));
        r.text("game_id", self.field("game_id"));
        r
    }
}
#[derive(Default)]
struct Counts {
    users: i64,
    guests: i64,
    converted: i64,
    games: i64,
    loadouts: i64,
    votes: i64,
}
struct Group {
    key: String,
    items: Vec<Record>,
}
impl Group {
    fn label(&self) -> String {
        format!("Slot {}", self.key)
    }
}
struct Day {
    date: String,
    values: BTreeMap<String, i64>,
    total: i64,
}
impl Day {
    fn value(&self, game: &str) -> i64 {
        self.values.get(game).copied().unwrap_or(0)
    }
    fn has(&self, game: &str) -> bool {
        self.values.contains_key(game)
    }
}
#[derive(Template)]
#[template(path = "admin/page.html")]
struct Page {
    kind: String,
    title: String,
    session: Session,
    alert: String,
    alert_class: String,
    ads: bool,
    form_action: String,
    form_method: String,
    counts: Counts,
    user: Record,
    game: Record,
    weapon: Record,
    attachment: Record,
    loadout: Record,
    users: Vec<Record>,
    games: Vec<Record>,
    weapons: Vec<Record>,
    attachments: Vec<Record>,
    loadouts: Vec<Record>,
    recent_users: Vec<Record>,
    recent_games: Vec<Record>,
    attachment_groups: Vec<Group>,
    chart_games: Vec<String>,
    days: Vec<Day>,
}
impl Page {
    fn new(session: Session) -> Self {
        Self {
            kind: "home".into(),
            title: "Dashboard".into(),
            session,
            alert: String::new(),
            alert_class: "alert-success".into(),
            ads: false,
            form_action: String::new(),
            form_method: String::new(),
            counts: Counts::default(),
            user: Record::default(),
            game: Record::default(),
            weapon: Record::default(),
            attachment: Record::default(),
            loadout: Record::default(),
            users: vec![],
            games: vec![],
            weapons: vec![],
            attachments: vec![],
            loadouts: vec![],
            recent_users: vec![],
            recent_games: vec![],
            attachment_groups: vec![],
            chart_games: vec![],
            days: vec![],
        }
    }
    fn asset(&self, value: &str) -> String {
        if value.starts_with("https://") || value.starts_with("http://") {
            value.into()
        } else {
            crate::asset_cache::url(&format!("/{}", value.trim_start_matches('/')))
        }
    }
    fn url(
        &self,
        action: &str,
        game: &str,
        weapon: &str,
        attachment: &str,
        loadout: &str,
        user: &str,
    ) -> String {
        let g = encode(game);
        let w = encode(weapon);
        let u = encode(user);
        match action {
            "adminDashboard" => "/admin".into(),
            "home" => "/".into(),
            "logout" => "/logout".into(),
            "createUser" => "/admin/user/create".into(),
            "modUsers" => "/admin/users".into(),
            "modGuests" => "/admin/guests".into(),
            "modConverted" => "/admin/converted".into(),
            "editUser" => format!("/admin/user/edit/{u}"),
            "deleteUser" => format!("/admin/user/delete/{u}"),
            "userSubmissions" => format!("/admin/user/submissions/{u}"),
            "admin.game.index" | "GameController@index" => "/admin/game".into(),
            "GameController@create" => "/admin/game/create".into(),
            "GameController@show" => format!("/admin/game/{g}"),
            "GameController@edit" => format!("/admin/game/{g}/edit"),
            "gameDelete" => format!("/admin/game/{g}/delete"),
            "WeaponController@create" => format!("/admin/{g}/weapon/create"),
            "weaponEdit" => format!("/admin/{g}/{w}/edit"),
            "weaponDelete" => format!("/admin/{g}/{w}/delete"),
            "weaponLoadouts" => format!("/admin/{g}/{w}"),
            "AttachmentController@create" => format!("/admin/{g}/attachment/create"),
            "attachmentEdit" => format!("/admin/{g}/attachment/{attachment}/edit"),
            "attachmentDelete" => format!("/admin/{g}/attachment/{attachment}/delete"),
            "AttachmentController@removeImage" => {
                format!("/admin/{g}/attachment/{attachment}/removeImage")
            }
            "ImportController@import" => format!("/admin/{g}/import"),
            "deleteLoadout" => format!("/admin/{g}/{w}/{loadout}/delete"),
            "showLoadout" => format!("/{g}/{w}/{loadout}"),
            "LoadoutController@listLoadouts" => "/admin/loadouts".into(),
            "LoadoutController@listVotes" => "/admin/votes".into(),
            "HelperController@compileCSS" => "/admin/compile-css".into(),
            "HelperController@toggleAds" => "/admin/toggleAds".into(),
            _ => "/admin".into(),
        }
    }
    fn render(self) -> Result<Response> {
        let html = Template::render(&self).map_err(|_| Error::internal())?;
        Ok(Html(html).into_response())
    }
}
#[derive(Debug)]
pub struct Error {
    status: StatusCode,
    message: &'static str,
}
type Result<T> = std::result::Result<T, Error>;
impl Error {
    fn internal() -> Self {
        Self {
            status: StatusCode::SERVICE_UNAVAILABLE,
            message: "The service is temporarily unavailable. Please try again.",
        }
    }
    fn invalid(message: &'static str) -> Self {
        Self {
            status: StatusCode::UNPROCESSABLE_ENTITY,
            message,
        }
    }
    fn missing() -> Self {
        Self {
            status: StatusCode::NOT_FOUND,
            message: "Not found.",
        }
    }
}
impl From<sqlx::Error> for Error {
    fn from(_: sqlx::Error) -> Self {
        Self::internal()
    }
}
impl IntoResponse for Error {
    fn into_response(self) -> Response {
        (self.status,Html(format!("<!doctype html><html><head><title>Admin - Game Loadouts</title></head><body><p>{}</p><a href=\"/admin\">Return to administration</a></body></html>",self.message))).into_response()
    }
}
pub fn routes() -> Router<Arc<AppState>> {
    Router::new()
        .route("/admin", any(handle))
        .route("/admin/", any(handle))
        .route("/admin/{*path}", any(handle))
        .layer(DefaultBodyLimit::max(20 * 1024 * 1024))
}
const USER_FIELDS: &str = "u.email,u.username,u.role,u.converted_guest,u.confirm_token,u.failed_attempts,u.disabled_until,u.created_at,u.updated_at,CAST(COALESCE(v.count,0) AS INTEGER) loadout_count";
const GAME_FIELDS: &str = "id,CAST(live AS INTEGER) live,COALESCE(image_url,'') image_url,COALESCE(thumb_url,'') thumb_url,COALESCE(header_url,'') header_url,COALESCE(short_name,'') short_name,COALESCE(theme_color,'green') theme_color,created_at,updated_at";
const WEAPON_FIELDS: &str = "CAST(w.id AS INTEGER) id,w.name,w.game_id,w.type,CAST(w.min_attachments AS INTEGER) min_attachments,CAST(w.max_attachments AS INTEGER) max_attachments,COALESCE(w.image_url,'') image_url,COALESCE(w.thumb_url,'') thumb_url,w.created_at,w.updated_at,(SELECT COUNT(*) FROM attachment_weapon a WHERE a.weapon_id=w.id) attachment_count,(SELECT COUNT(*) FROM loadouts l WHERE l.weapon_id=w.id) loadout_count";
const ATTACHMENT_FIELDS: &str = "CAST(id AS INTEGER) id,name,slot,game_id,COALESCE(image_url,'') image_url,COALESCE(thumb_url,'') thumb_url,created_at,updated_at";
async fn records(c: &Connection, sql: &str, binds: &[&str]) -> Result<Vec<Record>> {
    let mut q = sqlx::query_as(sql);
    for v in binds {
        q = q.bind(v);
    }
    Ok(q.fetch_all(c).await?)
}
async fn one(c: &Connection, sql: &str, binds: &[&str]) -> Result<Record> {
    records(c, sql, binds)
        .await?
        .into_iter()
        .next()
        .ok_or_else(Error::missing)
}
async fn game(c: &Connection, id: &str) -> Result<Record> {
    one(
        c,
        &format!("SELECT {GAME_FIELDS} FROM games WHERE id=?"),
        &[id],
    )
    .await
}
async fn weapon(c: &Connection, id: &str, name: &str) -> Result<Record> {
    let by_name = records(
        c,
        &format!("SELECT {WEAPON_FIELDS} FROM weapons w WHERE w.game_id=? AND w.name=?"),
        &[id, name],
    )
    .await?;
    if let Some(row) = by_name.into_iter().next() {
        return Ok(row);
    }
    if name.parse::<i64>().is_ok() {
        return one(
            c,
            &format!("SELECT {WEAPON_FIELDS} FROM weapons w WHERE w.game_id=? AND w.id=?"),
            &[id, name],
        )
        .await;
    }
    Err(Error::missing())
}
async fn user(c: &Connection, email: &str) -> Result<Record> {
    one(c,&format!("SELECT {USER_FIELDS} FROM users u LEFT JOIN (SELECT _key_user_id,COUNT(*) count FROM loadout_user GROUP BY _key_user_id) v ON v._key_user_id=u._key_email WHERE u.email=?"),&[email]).await
}
async fn attachment(c: &Connection, g: &str, id: &str) -> Result<Record> {
    one(
        c,
        &format!("SELECT {ATTACHMENT_FIELDS} FROM attachments WHERE game_id=? AND id=?"),
        &[g, id],
    )
    .await
}
async fn loadouts(c: &Connection, predicate: &str, args: &[&str]) -> Result<Vec<Record>> {
    let mut result=records(c,&format!("SELECT l.id,l.weapon_id,w.game_id,w.name weapon_name,l.created_at,l.updated_at FROM loadouts l JOIN weapons w ON w.id=l.weapon_id WHERE {predicate} ORDER BY l.updated_at DESC,l.id DESC"),args).await?;
    if result.is_empty() {
        return Ok(result);
    }
    let ids: Vec<i64> = result.iter().map(|x| x.number("id")).collect();
    let mut attached: BTreeMap<i64, Vec<Record>> = BTreeMap::new();
    for chunk in ids.chunks(200) {
        let sql = format!(
            "SELECT al.loadout_id,a.id,a.name,a.slot FROM attachment_loadout al JOIN attachments a ON a.id=al.attachment_id WHERE al.loadout_id IN ({}) ORDER BY al.id",
            vec!["?"; chunk.len()].join(",")
        );
        let mut q = sqlx::query_as::<Record>(&sql);
        for id in chunk {
            q = q.bind(id);
        }
        for row in q.fetch_all(c).await? {
            attached
                .entry(row.number("loadout_id"))
                .or_default()
                .push(row);
        }
    }
    for row in &mut result {
        row.attachments = attached.remove(&row.number("id")).unwrap_or_default();
    }
    Ok(result)
}
async fn groups(c: &Connection, g: &str, selected: Option<i64>) -> Result<Vec<Group>> {
    let mut items = records(
        c,
        &format!(
            "SELECT {ATTACHMENT_FIELDS} FROM attachments WHERE game_id=? ORDER BY _key_name,id"
        ),
        &[g],
    )
    .await?;
    let checked: BTreeSet<i64> = if let Some(id) = selected {
        sqlx::query_scalar("SELECT attachment_id FROM attachment_weapon WHERE weapon_id=?")
            .bind(id)
            .fetch_all(c)
            .await?
            .into_iter()
            .collect()
    } else {
        BTreeSet::new()
    };
    let mut result: BTreeMap<String, Vec<Record>> = BTreeMap::new();
    for mut r in items.drain(..) {
        r.text(
            "checked",
            if checked.contains(&r.number("id")) {
                "active"
            } else {
                ""
            },
        );
        result.entry(r.field("slot")).or_default().push(r);
    }
    Ok(result
        .into_iter()
        .map(|(key, items)| Group { key, items })
        .collect())
}
async fn read_page(
    state: &AppState,
    session: Session,
    uri: &axum::http::Uri,
    parts: &[String],
) -> Result<Response> {
    let c = state.pool.acquire().await?;
    let mut p = Page::new(session);
    p.ads = state.ads;
    let setting: Option<String> =
        sqlx::query_scalar("SELECT value FROM _rust_settings WHERE key='ads'")
            .fetch_optional(&c)
            .await?;
    if let Some(s) = setting {
        p.ads = s == "1";
    }
    let notice = uri
        .query()
        .and_then(|q| reqwest::Url::parse(&format!("https://fixture.invalid/?{q}")).ok())
        .and_then(|u| {
            u.query_pairs()
                .find(|(k, _)| k == "notice")
                .map(|(_, v)| v.into_owned())
        });
    if let Some(n) = notice {
        p.alert = match n.as_str() {
            "saved" => "Changes have been saved.",
            "deleted" => "The record has been deleted.",
            "imported" => "Import complete.",
            "ads" => "Advertisements updated.",
            "css" => "Stylesheet compilation complete.",
            _ => "",
        }
        .into();
    }
    match parts
        .iter()
        .map(String::as_str)
        .collect::<Vec<_>>()
        .as_slice()
    {
        [] => {
            let r:Record=one(&c,"SELECT (SELECT COUNT(*) FROM users WHERE role<>'Guest') users,(SELECT COUNT(*) FROM users WHERE role='Guest') guests,(SELECT COUNT(*) FROM users WHERE converted_guest=1) converted,(SELECT COUNT(*) FROM games) games,(SELECT COUNT(*) FROM loadouts) loadouts,(SELECT COUNT(*) FROM loadout_user) votes",&[]).await?;
            p.counts = Counts {
                users: r.number("users"),
                guests: r.number("guests"),
                converted: r.number("converted"),
                games: r.number("games"),
                loadouts: r.number("loadouts"),
                votes: r.number("votes"),
            };
            p.recent_users=records(&c,"SELECT email,username,role,created_at,updated_at FROM users WHERE role<>'Guest' ORDER BY created_at DESC LIMIT 5",&[]).await?;
            p.recent_games = records(
                &c,
                &format!("SELECT {GAME_FIELDS} FROM games ORDER BY created_at DESC LIMIT 5"),
                &[],
            )
            .await?;
            p.user = p.recent_users.last().cloned().unwrap_or_default();
        }
        ["users"] | ["guests"] | ["converted"] => {
            p.kind = "users".into();
            p.title = "User Administration".into();
            let predicate = match parts[0].as_str() {
                "guests" => "u.role='Guest'",
                "converted" => "u.converted_guest=1",
                _ => "u.role<>'Guest'",
            };
            let mut offset = 0;
            loop {
                let sql = format!(
                    "SELECT {USER_FIELDS} FROM users u LEFT JOIN (SELECT _key_user_id,COUNT(*) count FROM loadout_user GROUP BY _key_user_id) v ON v._key_user_id=u._key_email WHERE {predicate} ORDER BY u._key_email DESC LIMIT 500 OFFSET {offset}"
                );
                let batch = records(&c, &sql, &[]).await?;
                let count = batch.len();
                p.users.extend(batch);
                if count < 500 {
                    break;
                }
                offset += 500;
            }
        }
        ["user", "create"] => {
            p.kind = "user/create".into();
            p.title = "Create User".into();
            p.form_action = "/admin/user/create".into();
        }
        ["user", action, email] if matches!(*action, "edit" | "delete" | "submissions") => {
            p.user = user(&c, email).await?;
            p.kind = format!("user/{action}");
            p.title = format!("User {action}");
            p.form_action = format!("/admin/user/{action}/{}", encode(email));
            if *action == "submissions" {
                p.loadouts = loadouts(
                    &c,
                    "l.id IN (SELECT loadout_id FROM loadout_user WHERE user_id=?)",
                    &[email],
                )
                .await?;
            }
        }
        ["game"] => {
            p.kind = "games".into();
            p.title = "Game Administration".into();
            p.games = records(
                &c,
                &format!("SELECT {GAME_FIELDS} FROM games ORDER BY _key_id DESC"),
                &[],
            )
            .await?;
        }
        ["game", "create"] => {
            p.kind = "game/create".into();
            p.title = "Create Game".into();
            p.form_action = "/admin/game".into();
        }
        ["game", id] => {
            p.game = game(&c, id).await?;
            p.kind = "game/show".into();
            p.title = "Game Administration".into();
            p.weapons = records(
                &c,
                &format!("SELECT {WEAPON_FIELDS} FROM weapons w WHERE w.game_id=? ORDER BY w.id"),
                &[id],
            )
            .await?;
            p.attachments = records(
                &c,
                &format!("SELECT {ATTACHMENT_FIELDS} FROM attachments WHERE game_id=? ORDER BY id"),
                &[id],
            )
            .await?;
        }
        ["game", id, action] if matches!(*action, "edit" | "delete") => {
            p.game = game(&c, id).await?;
            p.kind = format!("game/{action}");
            p.title = format!("Game {action}");
            p.form_action = format!("/admin/game/{}", encode(id));
            p.form_method = if *action == "delete" { "DELETE" } else { "PUT" }.into();
        }
        [g, "import"] => {
            p.game = game(&c, g).await?;
            p.kind = "import/import".into();
            p.title = "Import".into();
            p.form_action = format!("/admin/{}/import", encode(g));
        }
        [g, "weapon", "create"] => {
            p.game = game(&c, g).await?;
            p.kind = "weapon/create".into();
            p.title = "Create Weapon".into();
            p.form_action = format!("/admin/{}/weapon/create", encode(g));
            p.attachment_groups = groups(&c, g, None).await?;
        }
        [g, "attachment", "create"] => {
            p.game = game(&c, g).await?;
            p.kind = "attachment/create".into();
            p.title = "Create Attachment".into();
            p.form_action = format!("/admin/{}/attachment/create", encode(g));
        }
        [g, "attachment", id, action] if matches!(*action, "edit" | "delete" | "removeImage") => {
            p.game = game(&c, g).await?;
            p.attachment = attachment(&c, g, id).await?;
            if *action == "removeImage" {
                return Ok(confirm(
                    &p.session,
                    &format!("/admin/{}/attachment/{}/removeImage", encode(g), id),
                    "Remove this attachment image?",
                )
                .into_response());
            }
            p.kind = format!("attachment/{action}");
            p.title = format!("Attachment {action}");
            p.form_action = format!("/admin/{}/attachment/{id}/{action}", encode(g));
        }
        [g, name] => {
            p.game = game(&c, g).await?;
            p.weapon = weapon(&c, g, name).await?;
            p.kind = "weapon/show".into();
            p.title = "Weapon Loadouts".into();
            p.loadouts = loadouts(&c, "l.weapon_id=?", &[&p.weapon.field("id")]).await?;
        }
        [g, name, action] if matches!(*action, "edit" | "delete") => {
            p.game = game(&c, g).await?;
            p.weapon = weapon(&c, g, name).await?;
            p.kind = format!("weapon/{action}");
            p.title = format!("Weapon {action}");
            p.form_action = format!("/admin/{}/{}/{action}", encode(g), p.weapon.field("id"));
            p.attachment_groups = groups(&c, g, Some(p.weapon.number("id"))).await?;
        }
        [g, name, id, "delete"] => {
            p.game = game(&c, g).await?;
            p.weapon = weapon(&c, g, name).await?;
            p.loadout=one(&c,"SELECT id,weapon_id,created_at,updated_at FROM loadouts WHERE id=? AND weapon_id=?",&[id,&p.weapon.field("id")]).await?;
            p.kind = "loadout/delete".into();
            p.title = "Delete Loadout".into();
            p.form_action = format!("/admin/{}/{}/{id}/delete", encode(g), encode(name));
        }
        ["votes"] | ["loadouts"] => {
            p.kind = "votes".into();
            p.title = if parts[0] == "votes" {
                "Votes"
            } else {
                "Loadouts"
            }
            .into();
            let sql = if parts[0] == "votes" {
                "SELECT w.game_id game,date(lu.created_at) date,COUNT(*) votes FROM weapons w JOIN loadouts l ON l.weapon_id=w.id JOIN loadout_user lu ON lu.loadout_id=l.id GROUP BY w._key_game_id,date(lu.created_at) ORDER BY date DESC,w._key_game_id"
            } else {
                "SELECT w.game_id game,date(l.created_at) date,COUNT(*) votes FROM weapons w JOIN loadouts l ON l.weapon_id=w.id GROUP BY w._key_game_id,date(l.created_at) ORDER BY date DESC,w._key_game_id"
            };
            let mut days: BTreeMap<String, BTreeMap<String, i64>> = BTreeMap::new();
            for r in records(&c, sql, &[]).await? {
                let game = r.field("game");
                if !p.chart_games.contains(&game) {
                    p.chart_games.push(game.clone());
                }
                days.entry(r.field("date"))
                    .or_default()
                    .insert(game, r.number("votes"));
            }
            p.days = days
                .into_iter()
                .rev()
                .map(|(date, values)| Day {
                    date,
                    total: values.values().sum(),
                    values,
                })
                .collect();
        }
        ["toggleAds"] => {
            return Ok(
                confirm(&p.session, "/admin/toggleAds", "Toggle advertisements?").into_response(),
            );
        }
        ["compile-css"] => {
            return Ok(confirm(
                &p.session,
                "/admin/compile-css",
                "Compile the packaged stylesheet?",
            )
            .into_response());
        }
        _ => return Err(Error::missing()),
    }
    p.render()
}
fn escape(s: &str) -> String {
    s.replace('&', "&amp;")
        .replace('<', "&lt;")
        .replace('>', "&gt;")
        .replace('"', "&quot;")
        .replace('\'', "&#39;")
}
fn confirm(session: &Session, action: &str, message: &str) -> Html<String> {
    Html(format!(
        "<!doctype html><html><head><title>Admin - Game Loadouts</title><link rel=\"stylesheet\" href=\"/css/bootstrap.min.css\"></head><body><div class=\"container\"><h2>{}</h2><form method=\"post\" action=\"{}\"><input type=\"hidden\" name=\"_token\" value=\"{}\"><button class=\"btn btn-primary\" type=\"submit\">Confirm</button> <a href=\"/admin\">Cancel</a></form></div></body></html>",
        escape(message),
        escape(action),
        escape(&session.csrf)
    ))
}
#[derive(Default)]
struct Input {
    fields: BTreeMap<String, String>,
    attachments: Vec<i64>,
    files: BTreeMap<String, Vec<u8>>,
    filenames: BTreeMap<String, String>,
}
impl Input {
    fn field(&self, name: &str) -> &str {
        self.fields.get(name).map(String::as_str).unwrap_or("")
    }
    fn name(&self, name: &str, max: usize) -> Result<String> {
        let s = self.field(name);
        if s.trim().is_empty() || s.chars().count() > max || sqlx::unicode_key(s).is_err() {
            return Err(Error::invalid(
                "Check the required fields and their lengths.",
            ));
        }
        Ok(s.into())
    }
    fn integer(&self, name: &str) -> Result<i64> {
        let n = self
            .field(name)
            .parse::<i64>()
            .map_err(|_| Error::invalid("Check the numeric fields."))?;
        if !(0..=1_000_000_000).contains(&n) {
            return Err(Error::invalid("Check the numeric fields."));
        }
        Ok(n)
    }
    fn optional_file(&self, name: &str) -> Option<&[u8]> {
        self.files
            .get(name)
            .filter(|b| !b.is_empty())
            .map(Vec::as_slice)
    }
}
async fn input(request: Request, state: &Arc<AppState>) -> Result<Input> {
    let multipart = request
        .headers()
        .get(header::CONTENT_TYPE)
        .and_then(|v| v.to_str().ok())
        .is_some_and(|v| v.starts_with("multipart/form-data"));
    let mut out = Input::default();
    if multipart {
        let mut form = Multipart::from_request(request, state)
            .await
            .map_err(|_| Error::invalid("The uploaded form could not be read."))?;
        let mut fields = 0;
        while let Some(field) = form
            .next_field()
            .await
            .map_err(|_| Error::invalid("The uploaded form could not be read."))?
        {
            fields += 1;
            if fields > 2000 {
                return Err(Error::invalid("Too many form fields."));
            }
            let name = field.name().unwrap_or("").to_owned();
            let filename = field.file_name().map(str::to_owned);
            let file = filename.is_some();
            let bytes = field
                .bytes()
                .await
                .map_err(|_| Error::invalid("The upload exceeds the supported size."))?;
            if file {
                if bytes.len() > 8 * 1024 * 1024 {
                    return Err(Error::invalid("Images must be at most 8 MB."));
                }
                out.filenames
                    .insert(name.clone(), filename.unwrap_or_default());
                out.files.insert(name, bytes.to_vec());
            } else {
                let value = String::from_utf8(bytes.to_vec())
                    .map_err(|_| Error::invalid("Fields must contain valid UTF-8."))?;
                if value.len() > 8192 {
                    return Err(Error::invalid("A field is too long."));
                }
                if name == "attachments[]" {
                    out.attachments.push(
                        value
                            .parse()
                            .map_err(|_| Error::invalid("Invalid attachment selection."))?,
                    );
                } else {
                    out.fields.insert(name, value);
                }
            }
        }
    } else {
        let bytes = to_bytes(request.into_body(), 128 * 1024)
            .await
            .map_err(|_| Error::invalid("The form is too large."))?;
        for (name, value) in reqwest::Url::parse(&format!(
            "https://fixture.invalid/?{}",
            std::str::from_utf8(&bytes).map_err(|_| Error::invalid("Invalid form."))?
        ))
        .map_err(|_| Error::invalid("Invalid form."))?
        .query_pairs()
        {
            if name == "attachments[]" {
                out.attachments.push(
                    value
                        .parse()
                        .map_err(|_| Error::invalid("Invalid attachment selection."))?,
                );
            } else {
                out.fields.insert(name.into_owned(), value.into_owned());
            }
        }
    }
    Ok(out)
}
pub async fn handle(
    State(state): State<Arc<AppState>>,
    Extension(session): Extension<Session>,
    OriginalUri(uri): OriginalUri,
    request: Request,
) -> Response {
    if state.admin.is_none() {
        return StatusCode::NOT_FOUND.into_response();
    }
    if !session.member() {
        return Redirect::to("/login").into_response();
    }
    if session.role != "Admin" {
        return Redirect::to("/").into_response();
    }
    let parts = match uri
        .path()
        .trim_matches('/')
        .split('/')
        .skip(1)
        .map(decode)
        .collect::<std::result::Result<Vec<_>, _>>()
    {
        Ok(p) => p,
        Err(_) => return StatusCode::BAD_REQUEST.into_response(),
    };
    let http_method = request.method().clone();
    let result = if matches!(
        http_method,
        axum::http::Method::GET | axum::http::Method::HEAD
    ) {
        read_page(&state, session, &uri, &parts).await
    } else if matches!(
        *request.method(),
        axum::http::Method::POST
            | axum::http::Method::PUT
            | axum::http::Method::PATCH
            | axum::http::Method::DELETE
    ) {
        match input(request, &state).await {
            Ok(mut form) => {
                if !form.fields.contains_key("_method") {
                    form.fields
                        .insert("_method".into(), http_method.as_str().into());
                }
                use subtle::ConstantTimeEq;
                let token = form.field("_token");
                if token.len() != 64
                    || session.csrf.len() != 64
                    || !bool::from(token.as_bytes().ct_eq(session.csrf.as_bytes()))
                {
                    Err(Error {
                        status: StatusCode::FORBIDDEN,
                        message: "This form has expired. Refresh the page and try again.",
                    })
                } else {
                    mutate(&state, &session, &parts, form).await
                }
            }
            Err(e) => Err(e),
        }
    } else {
        Err(Error {
            status: StatusCode::METHOD_NOT_ALLOWED,
            message: "Method not allowed.",
        })
    };
    let mut response = result.unwrap_or_else(IntoResponse::into_response);
    response
        .headers_mut()
        .insert(header::CACHE_CONTROL, HeaderValue::from_static("no-store"));
    response.headers_mut().insert(
        "x-robots-tag",
        HeaderValue::from_static("noindex, nofollow"),
    );
    response
}
async fn assert_admin(c: &Connection, session: &Session) -> Result<()> {
    let role: Option<String> =
        sqlx::query_scalar("SELECT role FROM users WHERE email=? AND _credential_hash=?")
            .bind(&session.email)
            .bind(&session.credential_hash)
            .fetch_optional(c)
            .await?;
    if role.as_deref() != Some("Admin") {
        return Err(Error {
            status: StatusCode::FORBIDDEN,
            message: "Administrator access is required.",
        });
    }
    Ok(())
}
async fn password(value: &str) -> Result<String> {
    if value.len() > 64 {
        return Err(Error::invalid("Passwords must be at most 64 bytes."));
    }
    let s = value.to_owned();
    tokio::task::spawn_blocking(move || bcrypt::hash(s, 12))
        .await
        .map_err(|_| Error::internal())?
        .map_err(|_| Error::internal())
}
async fn images(
    pool: &Pool,
    form: &Input,
    field: &str,
    width: Option<u32>,
    required: bool,
) -> Result<Option<(String, String)>> {
    match form.optional_file(field) {
        Some(bytes) => crate::media::store(pool, bytes, width)
            .await
            .map(Some)
            .map_err(Error::invalid),
        None if required => Err(Error::invalid("Upload the required image.")),
        None => Ok(None),
    }
}
async fn compatibility(tx: &WriteTransaction, g: &str, w: i64, ids: &[i64]) -> Result<()> {
    let wanted: BTreeSet<_> = ids.iter().copied().collect();
    if wanted.len() > 2000 {
        return Err(Error::invalid("Too many attachments selected."));
    }
    let available: BTreeSet<i64> = sqlx::query_scalar("SELECT id FROM attachments WHERE game_id=?")
        .bind(g)
        .fetch_all(&**tx)
        .await?
        .into_iter()
        .collect();
    if !wanted.is_subset(&available) {
        return Err(Error::invalid("Attachments must belong to this game."));
    }
    sqlx::query("DELETE FROM attachment_weapon WHERE weapon_id=?")
        .bind(w)
        .execute(&**tx)
        .await?;
    let created = stamp(tx).await?;
    for chunk in wanted.into_iter().collect::<Vec<_>>().chunks(50) {
        let rows = chunk
            .iter()
            .map(|id| {
                vec![
                    SqlValue::Integer(*id),
                    SqlValue::Integer(w),
                    SqlValue::Text(created.clone()),
                    SqlValue::Text(created.clone()),
                ]
            })
            .collect::<Vec<_>>();
        tx.insert_rows(
            "attachment_weapon",
            &["attachment_id", "weapon_id", "created_at", "updated_at"],
            &rows,
        )
        .await?;
    }
    Ok(())
}
async fn stamp(c: &Connection) -> Result<String> {
    Ok(
        sqlx::query_scalar("SELECT strftime('%Y-%m-%d %H:%M:%f','now')")
            .fetch_one(c)
            .await?,
    )
}
async fn mutate(
    state: &AppState,
    session: &Session,
    parts: &[String],
    form: Input,
) -> Result<Response> {
    let pool = state.admin.as_ref().ok_or_else(Error::missing)?;
    // Authenticate from current rows before accepting durable uploads; check again under the write lock.
    let c = pool.acquire().await?;
    assert_admin(&c, session).await?;
    drop(c);
    if parts == ["compile-css"] {
        crate::media::publish_stylesheet(pool)
            .await
            .map_err(Error::invalid)?;
        return Ok(Redirect::to("/admin?notice=css").into_response());
    }
    if parts.len() == 2 && parts[1] == "import" {
        return import_csv(pool, session, &parts[0], form).await;
    }
    let action: Vec<_> = parts.iter().map(String::as_str).collect();
    let is_game = matches!(action.as_slice(), ["game"] | ["game", _]);
    let is_weapon = matches!(action.as_slice(), [_, "weapon", "create"] | [_, _, "edit"]);
    let is_attachment = matches!(
        action.as_slice(),
        [_, "attachment", "create"] | [_, "attachment", _, "edit"]
    );
    let small = if is_game {
        images(pool, &form, "image", Some(525), parts.len() == 1).await?
    } else if is_weapon {
        images(
            pool,
            &form,
            "image",
            Some(128),
            parts.len() == 3 && parts[1] == "weapon",
        )
        .await?
    } else if is_attachment {
        images(pool, &form, "image", Some(64), false).await?
    } else {
        None
    };
    let header = if is_game {
        images(pool, &form, "header_image", None, parts.len() == 1).await?
    } else {
        None
    };
    let hashed = if matches!(action.as_slice(), ["user", "create"] | ["user", "edit", _])
        && (!form.field("password").is_empty() || parts.len() == 2)
    {
        Some(password(form.field("password")).await?)
    } else {
        None
    };
    let tx = pool.begin().await?;
    assert_admin(&tx, session).await?;
    let created = stamp(&tx).await?;
    let mut redirect = "/admin".to_owned();
    let mut notice = "saved";
    match action.as_slice() {
        ["toggleAds"] => {
            let current: Option<String> =
                sqlx::query_scalar("SELECT value FROM _rust_settings WHERE key='ads'")
                    .fetch_optional(&*tx)
                    .await?;
            let value = if current.as_deref().map(|s| s == "1").unwrap_or(state.ads) {
                "0"
            } else {
                "1"
            };
            sqlx::query("INSERT INTO _rust_settings(key,value) VALUES('ads',?) ON CONFLICT DO UPDATE SET value=excluded.value").bind(value).execute(&*tx).await?;
            notice = "ads";
        }
        ["user", "create"] => {
            let email = form.name("email", 128)?;
            let username = form.name("username", 128)?;
            let role = form.field("role");
            if !matches!(role, "Standard" | "Admin") {
                return Err(Error::invalid("Choose a valid role."));
            }
            let exists: Option<String> =
                sqlx::query_scalar("SELECT email FROM users WHERE email=? OR username=? LIMIT 1")
                    .bind(&email)
                    .bind(&username)
                    .fetch_optional(&*tx)
                    .await?;
            if exists.is_some() {
                return Err(Error::invalid("The email or username is already in use."));
            }
            sqlx::query("INSERT INTO users(email,username,password,role,converted_guest,remember_token,disabled_until,failed_attempts,confirm_token,created_at,updated_at) VALUES(?,?,?,?,0,'',?,0,'1',?,?)").bind(email).bind(username).bind(hashed.unwrap_or_default()).bind(role).bind(&created).bind(&created).bind(&created).execute(&*tx).await?;
            redirect = "/admin/users".into();
        }
        ["user", "edit", old] => {
            user(&tx, old).await?;
            let email = form.name("email", 128)?;
            let username = form.name("username", 128)?;
            let role = form.field("role");
            if !matches!(role, "Standard" | "Admin") {
                return Err(Error::invalid("Choose a valid role."));
            }
            let minutes = form.integer("disabled_until")?;
            let exists: Option<String> = sqlx::query_scalar(
                "SELECT email FROM users WHERE (email=? OR username=?) AND _key_email<>? LIMIT 1",
            )
            .bind(&email)
            .bind(&username)
            .bind_blob(sqlx::unicode_key(old)?)
            .fetch_optional(&*tx)
            .await?;
            if exists.is_some() {
                return Err(Error::invalid("The email or username is already in use."));
            }
            if let Some(hash) = hashed {
                sqlx::query("UPDATE users SET email=?,username=?,role=?,password=?,remember_token='',disabled_until=strftime('%Y-%m-%d %H:%M:%f','now',?||' minutes'),updated_at=? WHERE email=?").bind(&email).bind(&username).bind(role).bind(hash).bind(minutes.to_string()).bind(&created).bind(old).execute(&*tx).await?;
            } else {
                sqlx::query("UPDATE users SET email=?,username=?,role=?,disabled_until=strftime('%Y-%m-%d %H:%M:%f','now',?||' minutes'),updated_at=? WHERE email=?").bind(&email).bind(&username).bind(role).bind(minutes.to_string()).bind(&created).bind(old).execute(&*tx).await?;
            }
            // Converted FKs cascade comparison keys. Keep the raw natural references in step with them.
            let key = sqlx::unicode_key(&email)?;
            for table in ["loadouts", "loadout_user"] {
                sqlx::query(&format!(
                    "UPDATE {table} SET user_id=? WHERE _key_user_id=?"
                ))
                .bind(&email)
                .bind_blob(key.clone())
                .execute(&*tx)
                .await?;
            }
            redirect = "/admin/users".into();
        }
        ["user", "delete", email] => {
            user(&tx, email).await?;
            sqlx::query("DELETE FROM users WHERE email=?")
                .bind(email)
                .execute(&*tx)
                .await?;
            redirect = "/admin/users".into();
            notice = "deleted";
        }
        ["game"] => {
            let id = form.name("id", 128)?;
            let live = form.integer("live")?;
            if live > 1 {
                return Err(Error::invalid("Live must be 0 or 1."));
            }
            let theme = form.field("theme_color");
            if !matches!(theme, "cyan" | "green" | "orange" | "red" | "yellow") {
                return Err(Error::invalid("Choose a valid theme color."));
            }
            if !records(&tx, "SELECT id FROM games WHERE id=?", &[&id])
                .await?
                .is_empty()
            {
                return Err(Error::invalid("A game with that name already exists."));
            }
            let (image, thumb) =
                small.ok_or_else(|| Error::invalid("Upload the required image."))?;
            let header = header
                .ok_or_else(|| Error::invalid("Upload the required header image."))?
                .0;
            sqlx::query("INSERT INTO games(id,live,image_url,thumb_url,header_url,short_name,theme_color,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)").bind(id).bind(live).bind(image).bind(thumb).bind(header).bind(form.field("short_name")).bind(theme).bind(&created).bind(&created).execute(&*tx).await?;
            redirect = "/admin/game".into();
        }
        ["game", old] => {
            let game = game(&tx, old).await?;
            if form.field("_method") == "DELETE" {
                sqlx::query("DELETE FROM games WHERE id=?")
                    .bind(old)
                    .execute(&*tx)
                    .await?;
                notice = "deleted";
            } else {
                let id = form.name("id", 128)?;
                let live = form.integer("live")?;
                let theme = form.field("theme_color");
                if live > 1 || !matches!(theme, "cyan" | "green" | "orange" | "red" | "yellow") {
                    return Err(Error::invalid("Check the live flag and theme color."));
                }
                let (image, thumb) =
                    small.unwrap_or_else(|| (game.field("image_url"), game.field("thumb_url")));
                let header = header
                    .map(|x| x.0)
                    .unwrap_or_else(|| game.field("header_url"));
                sqlx::query("UPDATE games SET id=?,live=?,image_url=?,thumb_url=?,header_url=?,short_name=?,theme_color=?,updated_at=? WHERE id=?").bind(&id).bind(live).bind(image).bind(thumb).bind(header).bind(form.field("short_name")).bind(theme).bind(&created).bind(old).execute(&*tx).await?;
                let key = sqlx::unicode_key(&id)?;
                for table in ["weapons", "attachments"] {
                    sqlx::query(&format!(
                        "UPDATE {table} SET game_id=? WHERE _key_game_id=?"
                    ))
                    .bind(&id)
                    .bind_blob(key.clone())
                    .execute(&*tx)
                    .await?;
                }
            }
            redirect = "/admin/game".into();
        }
        [g, "weapon", "create"] => {
            game(&tx, g).await?;
            let name = form.name("name", 128)?;
            let kind = form.name("type", 64)?;
            let minimum = form.integer("min_attachments")?;
            let maximum = form.integer("max_attachments")?;
            if minimum > maximum {
                return Err(Error::invalid(
                    "Minimum attachments cannot exceed the maximum.",
                ));
            }
            let (image, thumb) =
                small.ok_or_else(|| Error::invalid("Upload the required image."))?;
            let id=sqlx::query("INSERT INTO weapons(name,game_id,image_url,thumb_url,min_attachments,max_attachments,type,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)").bind(name).bind(g).bind(image).bind(thumb).bind(minimum).bind(maximum).bind(kind).bind(&created).bind(&created).execute(&*tx).await?.last_insert_id();
            compatibility(&tx, g, id, &form.attachments).await?;
            redirect = format!("/admin/game/{}", encode(g));
        }
        [g, name, "edit"] => {
            let weapon = weapon(&tx, g, name).await?;
            let id = weapon.number("id");
            let name = form.name("name", 128)?;
            let kind = form.name("type", 64)?;
            let minimum = form.integer("min_attachments")?;
            let maximum = form.integer("max_attachments")?;
            if minimum > maximum {
                return Err(Error::invalid(
                    "Minimum attachments cannot exceed the maximum.",
                ));
            }
            let (image, thumb) =
                small.unwrap_or_else(|| (weapon.field("image_url"), weapon.field("thumb_url")));
            sqlx::query("UPDATE weapons SET name=?,type=?,image_url=?,thumb_url=?,min_attachments=?,max_attachments=?,updated_at=? WHERE id=?").bind(name).bind(kind).bind(image).bind(thumb).bind(minimum).bind(maximum).bind(&created).bind(id).execute(&*tx).await?;
            compatibility(&tx, g, id, &form.attachments).await?;
            redirect = format!("/admin/game/{}", encode(g));
        }
        [g, name, "delete"] => {
            let weapon = weapon(&tx, g, name).await?;
            sqlx::query("DELETE FROM weapons WHERE id=?")
                .bind(weapon.number("id"))
                .execute(&*tx)
                .await?;
            redirect = format!("/admin/game/{}", encode(g));
            notice = "deleted";
        }
        [g, "attachment", "create"] => {
            game(&tx, g).await?;
            let name = form.name("name", 128)?;
            let slot = form.name("slot", 64)?;
            let (image, thumb) = small.unwrap_or_default();
            sqlx::query("INSERT INTO attachments(name,slot,game_id,image_url,thumb_url,created_at,updated_at) VALUES(?,?,?,?,?,?,?)").bind(name).bind(slot).bind(g).bind(image).bind(thumb).bind(&created).bind(&created).execute(&*tx).await?;
            redirect = format!("/admin/game/{}", encode(g));
        }
        [g, "attachment", id, "edit"] => {
            let attachment = attachment(&tx, g, id).await?;
            let name = form.name("name", 128)?;
            let slot = form.name("slot", 64)?;
            let (image, thumb) = small
                .unwrap_or_else(|| (attachment.field("image_url"), attachment.field("thumb_url")));
            sqlx::query("UPDATE attachments SET name=?,slot=?,image_url=?,thumb_url=?,updated_at=? WHERE id=?").bind(name).bind(slot).bind(image).bind(thumb).bind(&created).bind(id.parse::<i64>().map_err(|_|Error::missing())?).execute(&*tx).await?;
            redirect = format!("/admin/game/{}", encode(g));
        }
        [g, "attachment", id, "removeImage"] => {
            attachment(&tx, g, id).await?;
            sqlx::query("UPDATE attachments SET image_url='',thumb_url='',updated_at=? WHERE id=?")
                .bind(&created)
                .bind(id.parse::<i64>().map_err(|_| Error::missing())?)
                .execute(&*tx)
                .await?;
            redirect = format!("/admin/game/{}", encode(g));
        }
        [g, "attachment", id, "delete"] => {
            attachment(&tx, g, id).await?;
            let id = id.parse::<i64>().map_err(|_| Error::missing())?;
            sqlx::query("DELETE FROM loadouts WHERE id IN (SELECT loadout_id FROM attachment_loadout WHERE attachment_id=?)").bind(id).execute(&*tx).await?;
            sqlx::query("DELETE FROM attachments WHERE id=?")
                .bind(id)
                .execute(&*tx)
                .await?;
            redirect = format!("/admin/game/{}", encode(g));
            notice = "deleted";
        }
        [g, name, id, "delete"] => {
            let weapon = weapon(&tx, g, name).await?;
            let id = id.parse::<i64>().map_err(|_| Error::missing())?;
            let found: Option<i64> =
                sqlx::query_scalar("SELECT id FROM loadouts WHERE id=? AND weapon_id=?")
                    .bind(id)
                    .bind(weapon.number("id"))
                    .fetch_optional(&*tx)
                    .await?;
            if found.is_none() {
                return Err(Error::missing());
            }
            sqlx::query("DELETE FROM loadouts WHERE id=?")
                .bind(id)
                .execute(&*tx)
                .await?;
            redirect = format!("/admin/{}/{}", encode(g), encode(name));
            notice = "deleted";
        }
        _ => return Err(Error::missing()),
    }
    tx.commit().await?;
    Ok(Redirect::to(&format!("{redirect}?notice={notice}")).into_response())
}
async fn import_csv(pool: &Pool, session: &Session, g: &str, form: Input) -> Result<Response> {
    let filename = form
        .filenames
        .get("spreadsheet")
        .map(String::as_str)
        .unwrap_or("");
    if !filename.to_ascii_lowercase().ends_with(".csv") {
        return Err(Error::invalid("Only .csv is supported."));
    }
    let bytes = form
        .optional_file("spreadsheet")
        .ok_or_else(|| Error::invalid("Spreadsheet missing."))?;
    if bytes.len() > 2 * 1024 * 1024 {
        return Err(Error::invalid("The CSV must be at most 2 MB."));
    }
    let mut csv = csv::ReaderBuilder::new()
        .has_headers(false)
        .flexible(true)
        .from_reader(bytes);
    let rows: Vec<Vec<String>> = csv
        .records()
        .map(|r| r.map(|r| r.iter().map(str::to_owned).collect()))
        .collect::<std::result::Result<_, _>>()
        .map_err(|_| Error::invalid("The CSV could not be read."))?;
    if rows.len() < 5 || rows[0].len() < 3 || rows.len() > 10000 || rows[0].len() > 202 {
        return Err(Error::invalid("The CSV has an unsupported shape."));
    }
    let width = rows[0].len();
    if rows.iter().take(4).any(|r| r.len() != width) {
        return Err(Error::invalid(
            "The first four CSV rows must have matching columns.",
        ));
    }
    struct WeaponRow {
        name: String,
        kind: String,
        min: i64,
        max: i64,
        id: i64,
    }
    struct AttachmentRow {
        name: String,
        slot: String,
        id: i64,
        weapons: Vec<usize>,
    }
    let valid = |s: &str, max: usize| {
        !s.trim().is_empty() && s.chars().count() <= max && sqlx::unicode_key(s).is_ok()
    };
    let mut weapons = Vec::new();
    for (col, name) in rows[0].iter().enumerate().skip(2) {
        let name = name.clone();
        let kind = rows[1][col].clone();
        let min = rows[2][col]
            .parse::<i64>()
            .map_err(|_| Error::invalid("Invalid CSV attachment limits."))?;
        let max = rows[3][col]
            .parse::<i64>()
            .map_err(|_| Error::invalid("Invalid CSV attachment limits."))?;
        if !valid(&name, 128) || !valid(&kind, 64) || min < 0 || max < min || max > 2000 {
            return Err(Error::invalid(
                "Check the CSV weapon names, types and attachment limits.",
            ));
        }
        weapons.push(WeaponRow {
            name,
            kind,
            min,
            max,
            id: 0,
        });
    }
    let mut attachments = Vec::new();
    for row in rows.iter().skip(5) {
        if row.iter().all(|s| s.is_empty()) {
            continue;
        }
        if row.len() != width || !valid(&row[0], 128) || !valid(&row[1], 64) {
            return Err(Error::invalid("Check the CSV attachment rows."));
        }
        let selected = (2..width)
            .filter(|i| !row[*i].is_empty() && row[*i] != "0")
            .map(|i| i - 2)
            .collect::<Vec<_>>();
        attachments.push(AttachmentRow {
            name: row[0].clone(),
            slot: row[1].clone(),
            id: 0,
            weapons: selected,
        });
    }
    if attachments.iter().map(|r| r.weapons.len()).sum::<usize>() > 100000 {
        return Err(Error::invalid(
            "The CSV contains too many compatibility links.",
        ));
    }
    let tx = pool.begin().await?;
    assert_admin(&tx, session).await?;
    game(&tx, g).await?;
    let created = stamp(&tx).await?;
    let mut known_attachments = BTreeMap::new();
    for r in records(
        &tx,
        "SELECT id,name,slot FROM attachments WHERE game_id=? ORDER BY id",
        &[g],
    )
    .await?
    {
        known_attachments
            .entry((
                sqlx::unicode_key(&r.field("name"))?,
                sqlx::unicode_key(&r.field("slot"))?,
            ))
            .or_insert(r.number("id"));
    }
    // Add missing attachments in bounded batches; retries reuse canonical existing keys.
    let mut pending: BTreeMap<(Vec<u8>, Vec<u8>), (String, String)> = BTreeMap::new();
    for r in &attachments {
        let key = (sqlx::unicode_key(&r.name)?, sqlx::unicode_key(&r.slot)?);
        if !known_attachments.contains_key(&key) {
            pending
                .entry(key)
                .or_insert((r.name.clone(), r.slot.clone()));
        }
    }
    let pending: Vec<_> = pending.into_iter().collect();
    for chunk in pending.chunks(50) {
        let rows = chunk
            .iter()
            .map(|(_, (name, slot))| {
                vec![
                    SqlValue::Text(name.clone()),
                    SqlValue::Text(slot.clone()),
                    SqlValue::Text(g.into()),
                    SqlValue::Text(String::new()),
                    SqlValue::Text(String::new()),
                    SqlValue::Text(created.clone()),
                    SqlValue::Text(created.clone()),
                ]
            })
            .collect::<Vec<_>>();
        let ids = tx
            .insert_rows(
                "attachments",
                &[
                    "name",
                    "slot",
                    "game_id",
                    "image_url",
                    "thumb_url",
                    "created_at",
                    "updated_at",
                ],
                &rows,
            )
            .await?;
        for ((key, _), id) in chunk.iter().zip(ids) {
            known_attachments.insert(key.clone(), id);
        }
    }
    for r in &mut attachments {
        r.id = known_attachments[&(sqlx::unicode_key(&r.name)?, sqlx::unicode_key(&r.slot)?)];
    }
    let mut known_weapons = BTreeMap::new();
    for r in records(
        &tx,
        "SELECT id,name,type FROM weapons WHERE game_id=? ORDER BY id",
        &[g],
    )
    .await?
    {
        known_weapons
            .entry((
                sqlx::unicode_key(&r.field("name"))?,
                sqlx::unicode_key(&r.field("type"))?,
            ))
            .or_insert(r.number("id"));
    }
    let mut pending = BTreeMap::new();
    for (i, r) in weapons.iter().enumerate() {
        let key = (sqlx::unicode_key(&r.name)?, sqlx::unicode_key(&r.kind)?);
        if !known_weapons.contains_key(&key) {
            pending.entry(key).or_insert(i);
        }
    }
    let pending: Vec<_> = pending.into_iter().collect();
    for chunk in pending.chunks(50) {
        let rows = chunk
            .iter()
            .map(|(_, i)| {
                let r = &weapons[*i];
                vec![
                    SqlValue::Text(r.name.clone()),
                    SqlValue::Text(g.into()),
                    SqlValue::Text(String::new()),
                    SqlValue::Text(String::new()),
                    SqlValue::Integer(r.min),
                    SqlValue::Integer(r.max),
                    SqlValue::Text(r.kind.clone()),
                    SqlValue::Text(created.clone()),
                    SqlValue::Text(created.clone()),
                ]
            })
            .collect::<Vec<_>>();
        let ids = tx
            .insert_rows(
                "weapons",
                &[
                    "name",
                    "game_id",
                    "image_url",
                    "thumb_url",
                    "min_attachments",
                    "max_attachments",
                    "type",
                    "created_at",
                    "updated_at",
                ],
                &rows,
            )
            .await?;
        for ((key, _), id) in chunk.iter().zip(ids) {
            known_weapons.insert(key.clone(), id);
        }
    }
    for r in &mut weapons {
        r.id = known_weapons[&(sqlx::unicode_key(&r.name)?, sqlx::unicode_key(&r.kind)?)];
    }
    let mut existing:BTreeSet<(i64,i64)>=records(&tx,"SELECT aw.attachment_id,aw.weapon_id FROM attachment_weapon aw JOIN weapons w ON w.id=aw.weapon_id WHERE w.game_id=?",&[g]).await?.into_iter().map(|r|(r.number("attachment_id"),r.number("weapon_id"))).collect();
    let mut wanted = Vec::new();
    for attachment in attachments {
        for i in attachment.weapons {
            let pair = (attachment.id, weapons[i].id);
            if existing.insert(pair) {
                wanted.push(pair);
            }
        }
    }
    for chunk in wanted.chunks(50) {
        let rows = chunk
            .iter()
            .map(|(a, w)| {
                vec![
                    SqlValue::Integer(*a),
                    SqlValue::Integer(*w),
                    SqlValue::Text(created.clone()),
                    SqlValue::Text(created.clone()),
                ]
            })
            .collect::<Vec<_>>();
        tx.insert_rows(
            "attachment_weapon",
            &["attachment_id", "weapon_id", "created_at", "updated_at"],
            &rows,
        )
        .await?;
    }
    tx.commit().await?;
    Ok(Redirect::to(&format!("/admin/game/{}?notice=imported", encode(g))).into_response())
}
