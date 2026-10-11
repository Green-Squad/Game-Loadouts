use crate::{
    account::{self, Accounts, Session},
    db::*,
    model::*,
};
use askama::Template;
use axum::{
    Extension, Router,
    extract::{Form, OriginalUri, Query, State},
    http::{HeaderValue, StatusCode},
    middleware,
    response::{Html, IntoResponse, Redirect, Response},
    routing::get,
};
use serde::Deserialize;
use sqlx::MySqlPool;
use std::{
    collections::BTreeMap,
    sync::Arc,
    time::{Duration, Instant},
};
use tower::Layer;
use tower_http::{
    services::{ServeDir, ServeFile},
    set_header::SetResponseHeaderLayer,
};

pub struct AppState {
    pub accounts: Option<Arc<Accounts>>,
    pub ads: bool,
    pub analytics_id: String,
    pub pool: MySqlPool,
    pub query_timeout: Duration,
    pub public_url: String,
    pub media_origin: String,
    pub disqus: bool,
    pub metrics: bool,
    pub search_mode: String,
    pub search_api_url: String,
    pub http: reqwest::Client,
}
#[derive(Template)]
#[template(path = "page.html")]
struct Page {
    session: Session,
    interactive: bool,
    screen: String,
    token: String,
    site_key: String,
    disqus_auth: String,
    account_origin: String,
    ads: bool,
    analytics_id: String,
    kind: String,
    title: String,
    description: String,
    theme: String,
    nav: Vec<Game>,
    footer: Vec<Game>,
    games: Vec<Game>,
    columns: Vec<Vec<Game>>,
    popular: Vec<Popular>,
    game: Game,
    weapon: Weapon,
    loadout: Loadout,
    weapons: Vec<WeaponGroup>,
    attachments: Vec<AttachmentGroup>,
    loadouts: Vec<Loadout>,
    recent: Vec<Loadout>,
    stats: Vec<Stats>,
    chart_json: String,
    results: Vec<SearchItem>,
    query: String,
    status: u16,
    message: String,
    media_origin: String,
    disqus: bool,
    public_url: String,
}
impl Page {
    fn new(state: &AppState) -> Self {
        Self {
            session: Session::default(),
            disqus_auth: String::new(),
            account_origin: state
                .accounts
                .as_ref()
                .map(|a| a.account_url.clone())
                .unwrap_or_default(),
            ads: state.ads,
            analytics_id: state.analytics_id.clone(),
            interactive: state.accounts.is_some(),
            screen: String::new(),
            token: String::new(),
            site_key: state
                .accounts
                .as_ref()
                .map(|a| a.site_key.clone())
                .unwrap_or_default(),
            kind: String::new(),
            title: "Game Loadouts".into(),
            description: String::new(),
            theme: "green".into(),
            nav: vec![],
            footer: vec![],
            games: vec![],
            columns: vec![],
            popular: vec![],
            game: Game::default(),
            weapon: Weapon::default(),
            loadout: Loadout::default(),
            weapons: vec![],
            attachments: vec![],
            loadouts: vec![],
            recent: vec![],
            stats: vec![],
            chart_json: "{}".into(),
            results: vec![],
            query: String::new(),
            status: 200,
            message: String::new(),
            media_origin: state.media_origin.clone(),
            disqus: state.disqus,
            public_url: state.public_url.clone(),
        }
    }
    fn recent_links(&mut self, recent: &[Game]) {
        self.nav = recent
            .iter()
            .take(5)
            .filter(|g| g.live == 1)
            .cloned()
            .collect();
        self.footer = recent
            .iter()
            .take(4)
            .filter(|g| g.live == 1)
            .cloned()
            .collect();
    }
    fn asset(&self, path: &str) -> String {
        if path.is_empty() {
            return String::new();
        }
        if path.starts_with("https://") || path.starts_with("http://") {
            return path.into();
        }
        let path = path.trim_start_matches('/');
        if path.starts_with("uploads/") && !self.media_origin.is_empty() {
            format!("{}/{}", self.media_origin, path)
        } else {
            format!("/{path}")
        }
    }
}
fn error_page(state: &AppState, code: u16, msg: &str) -> Page {
    let mut p = Page::new(state);
    p.kind = "error".into();
    p.status = code;
    p.title = code.to_string();
    p.message = msg.into();
    p
}
pub fn app(state: Arc<AppState>, assets: &str) -> Router {
    let cache = SetResponseHeaderLayer::if_not_present(
        axum::http::header::CACHE_CONTROL,
        HeaderValue::from_static("public, max-age=3600"),
    );
    let mut router = Router::new()
        .route("/healthz", get(|| async { "ok\n" }))
        .route("/", get(page))
        .route("/search", get(search_get).post(search_post))
        .route("/search_weapons", get(autocomplete))
        .route("/{*path}", get(page).post(account::write_loadout));
    for path in [
        "/login",
        "/join",
        "/logout",
        "/account",
        "/reminder",
        "/resend-confirmation",
        "/password/reset",
    ] {
        router = router.route(path, get(account::get_account).post(account::post_account));
    }
    router = router
        .route("/password/reset/{token}", get(account::get_account))
        .route("/user/confirm/{token}", get(account::confirm))
        .route(
            "/robots.txt",
            get(|| async {
                (
                    [(
                        axum::http::header::CONTENT_TYPE,
                        "text/plain; charset=utf-8",
                    )],
                    "User-agent: *\nDisallow: /admin\nDisallow: /account\nDisallow: /submissions\n",
                )
            }),
        )
        .route_layer(middleware::from_fn_with_state(
            state.clone(),
            account::session_layer,
        ));
    for dir in [
        "css",
        "js",
        "fonts",
        "font-awesome",
        "img",
        "uploads",
        "rs-plugin",
    ] {
        router = router.nest_service(
            &format!("/{dir}"),
            cache
                .clone()
                .layer(ServeDir::new(format!("{assets}/{dir}"))),
        );
    }
    router
        .route_service(
            "/favicon.ico",
            cache.layer(ServeFile::new(format!("{assets}/favicon.ico"))),
        )
        .with_state(state)
}
fn instrument(
    mut response: Response,
    db: &Db<'_>,
    start: Instant,
    route: &str,
    metrics: bool,
) -> Response {
    // Never log request paths/queries, SQL errors or connect options (may contain private data).
    tracing::info!(
        route,
        status = response.status().as_u16(),
        queries = db.count,
        db_ms = db.millis,
        elapsed_ms = start.elapsed().as_secs_f64() * 1000.0,
        "request"
    );
    response.headers_mut().insert(
        axum::http::header::CACHE_CONTROL,
        HeaderValue::from_static("no-store"),
    );
    if metrics {
        response.headers_mut().insert(
            "server-timing",
            HeaderValue::from_str(&format!(
                "db;dur={:.3}, queries;desc=\"{}\"",
                db.millis, db.count
            ))
            .unwrap(),
        );
        response.headers_mut().insert(
            "x-db-queries",
            HeaderValue::from_str(&db.count.to_string()).unwrap(),
        );
    }
    response
}
pub(crate) async fn account_page(
    state: &AppState,
    session: &Session,
    screen: &str,
    status: u16,
    message: &str,
    token: &str,
) -> Response {
    let mut p = Page::new(state);
    p.kind = "account".into();
    p.title = "Account - Game Loadouts".into();
    p.session = session.clone();
    p.disqus_auth = state
        .accounts
        .as_ref()
        .map(|a| a.disqus_auth(session))
        .unwrap_or_default();
    p.screen = screen.into();
    p.status = status;
    p.message = message.into();
    p.token = token.into();
    if state.accounts.is_some()
        && let Ok(recent) = Db::new(&state.pool, state.query_timeout).recent().await
    {
        p.recent_links(&recent);
    }
    render(p)
}
fn render(p: Page) -> Response {
    let status = StatusCode::from_u16(p.status).unwrap_or(StatusCode::INTERNAL_SERVER_ERROR);
    match p.render() {
        Ok(html) => (status, Html(html)).into_response(),
        Err(_) => (StatusCode::INTERNAL_SERVER_ERROR, "Rendering failed").into_response(),
    }
}
async fn page(
    State(state): State<Arc<AppState>>,
    Extension(session): Extension<Session>,
    OriginalUri(uri): OriginalUri,
) -> Response {
    let start = Instant::now();
    let mut db = Db::new(&state.pool, state.query_timeout);
    let parts: Result<Vec<_>, _> = uri
        .path()
        .trim_matches('/')
        .split('/')
        .filter(|p| !p.is_empty())
        .map(decode)
        .collect();
    let (route, result) = match parts {
        Ok(parts) => {
            let route = match parts.as_slice() {
                [] => "home",
                [s] if s == "games" => "games",
                [s] if s == "stats" => "stats",
                [s] if s == "terms" => "terms",
                [s] if s == "sitemap.xml" => "sitemap",
                [_] => "game",
                [_, _] => "weapon",
                [_, _, _] => "loadout",
                _ => "missing",
            };
            (route, build_page(&state, &mut db, &parts, &session).await)
        }
        Err(_) => (
            "invalid",
            Ok(render(error_page(&state, 400, "Invalid URL encoding"))),
        ),
    };
    let response = match result {
        Ok(response) => response,
        Err(DbError::Timeout) => render(error_page(
            &state,
            503,
            "The database request timed out. Please try again.",
        )),
        Err(DbError::Query) => {
            tracing::error!(route, "database request failed");
            render(error_page(
                &state,
                503,
                "The service is temporarily unavailable.",
            ))
        }
    };
    instrument(response, &db, start, route, state.metrics)
}
async fn build_page(
    state: &AppState,
    db: &mut Db<'_>,
    parts: &[String],
    session: &Session,
) -> Result<Response, DbError> {
    if parts.len() == 1 && parts[0] == "sitemap.xml" {
        return sitemap(state, db).await;
    }
    let mut p = Page::new(state);
    p.session = session.clone();
    p.disqus_auth = state
        .accounts
        .as_ref()
        .map(|a| a.disqus_auth(session))
        .unwrap_or_default();
    if parts.len() == 1 && parts[0] == "submissions" && state.accounts.is_some() {
        if !session.member() {
            return Ok(Redirect::to("/login").into_response());
        }
        p.kind = "submissions".into();
        p.title = "My submissions - Game Loadouts".into();
        p.recent_links(&db.recent().await?);
        p.loadouts=db.rows(&loadout_sql("EXISTS (SELECT 1 FROM loadout_user mine WHERE mine.loadout_id=l.id AND mine.user_id=?)", "(SELECT MAX(updated_at) FROM loadout_user mine WHERE mine.loadout_id=l.id AND mine.user_id=?) DESC,l.id"), &[session.email.clone(),session.email.clone(),session.email.clone()]).await?;
        db.attach(&mut [&mut p.loadouts]).await?;
        return Ok(render(p));
    }
    let recent = if parts.len() == 1 && parts[0] == "games" {
        db.rows(
            &format!("SELECT {GAME_COLUMNS} FROM games ORDER BY created_at DESC,id"),
            &[],
        )
        .await?
    } else {
        db.recent().await?
    };
    p.recent_links(&recent);
    match parts {
        [] => {
            p.kind = "home".into();
            p.description="Game Loadouts contains Titanfall, Battlefield 4 (BF4), and Call of Duty Ghosts (COD Ghosts) loadouts for which you can find the best way to outfit your favorite weapons.".into();
            p.games=db.rows(&format!("SELECT {GAME_COLUMNS} FROM games WHERE live=1 ORDER BY created_at DESC,id LIMIT 3"),&[]).await?;
            if !p.games.is_empty() {
                let sql = p
                    .games
                    .iter()
                    .map(|_| {
                        format!(
                            "({})",
                            loadout_sql("w.game_id=? AND v.votes>0", "votes DESC,l.id LIMIT 5")
                        )
                    })
                    .collect::<Vec<_>>()
                    .join(" UNION ALL ");
                let mut loads: Vec<Loadout> = db
                    .rows(
                        &sql,
                        &p.games
                            .iter()
                            .flat_map(|g| [g.id.clone(), g.id.clone()])
                            .collect::<Vec<_>>(),
                    )
                    .await?;
                db.attach(&mut [&mut loads]).await?;
                p.popular = p
                    .games
                    .iter()
                    .map(|g| Popular {
                        game: g.clone(),
                        loadouts: loads
                            .iter()
                            .filter(|l| l.game_id == g.id)
                            .cloned()
                            .collect(),
                    })
                    .collect();
            }
        }
        [s] if s == "terms" => {
            p.kind = "terms".into();
            p.title = "Terms of Service".into();
            p.description = "Game Loadouts Terms of Service".into();
        }
        [s] if s == "games" => {
            p.kind = "games".into();
            p.title = "Games".into();
            p.games = recent.into_iter().filter(|g| g.live == 1).collect();
            let mut metadata_games = p.games.clone();
            metadata_games.sort_by(|a, b| a.id.cmp(&b.id));
            p.description = format!(
                "Find the best weapon loadouts for {}.",
                pretty(
                    &metadata_games
                        .iter()
                        .map(|g| g.id.clone())
                        .collect::<Vec<_>>()
                )
            );
            p.columns = (0..2)
                .map(|i| p.games.iter().skip(i).step_by(2).cloned().collect())
                .collect();
        }
        [s] if s == "stats" => {
            p.kind = "stats".into();
            p.title = "Game Loadouts Stats".into();
            p.description =
                "View the stats we have collected on all the game loadout submissions.".into();
            p.stats=db.rows("SELECT A.game_id game, CAST(A.votes AS SIGNED) votes, CAST(B.loadouts AS SIGNED) loadouts FROM (SELECT w.game_id, COUNT(lu.loadout_id) votes FROM weapons w JOIN loadouts l ON l.weapon_id=w.id JOIN loadout_user lu ON lu.loadout_id=l.id GROUP BY w.game_id) A JOIN (SELECT w.game_id, COUNT(l.id) loadouts FROM weapons w JOIN loadouts l ON l.weapon_id=w.id GROUP BY w.game_id) B ON A.game_id=B.game_id ORDER BY A.game_id",&[]).await?;
            let days: Vec<Day>=db.rows("SELECT d.game,CAST(d.vote_date AS CHAR) date,CAST(d.votes AS SIGNED) votes FROM (SELECT w.game_id game,DATE(lu.created_at) vote_date,COUNT(*) votes FROM weapons w JOIN loadouts l ON l.weapon_id=w.id JOIN loadout_user lu ON lu.loadout_id=l.id WHERE DATE(lu.created_at)>=DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY w.game_id,DATE(lu.created_at)) d ORDER BY d.vote_date,d.game",&[]).await?;
            let dates: std::collections::BTreeSet<_> =
                days.iter().map(|d| d.date.clone()).collect();
            let mut names = vec![];
            for d in &days {
                if !names.contains(&d.game) {
                    names.push(d.game.clone());
                }
            }
            let colors = [
                "rgba(27,130,212,1)",
                "rgba(165,191,114,1)",
                "rgba(176,50,50,1)",
                "rgba(199,182,37,1)",
                "rgba(191,43,133,1)",
                "rgba(90,190,202,1)",
                "rgba(200,200,200,1)",
                "rgba(29,187,133,1)",
                "rgba(100,95,170,1)",
                "rgba(140,100,27,1)",
            ];
            let datasets=names.iter().enumerate().map(|(i,name)|serde_json::json!({"label":name,"fillColor":"rgba(220,220,220,0)","strokeColor":colors[i%colors.len()],"pointColor":colors[i%colors.len()],"pointStrokeColor":"#fff","data":dates.iter().map(|date|days.iter().find(|d|&d.date==date && &d.game==name).map_or(0,|d|d.votes)).collect::<Vec<_>>()})).collect::<Vec<_>>();
            p.chart_json = serde_json::json!({"labels":dates,"datasets":datasets})
                .to_string()
                .replace('<', "\\u003c");
        }
        [game] | [game, _] | [game, _, _] => {
            // Explicitly reserve omitted account/admin URLs instead of treating them as game names.
            if [
                "login",
                "join",
                "logout",
                "account",
                "submissions",
                "reminder",
                "password",
                "user",
                "admin",
            ]
            .contains(&game.as_str())
            {
                return Ok(render(error_page(
                    state,
                    404,
                    "This feature is unavailable in the read-only experiment.",
                )));
            }
            let Some(g) = db.game(game).await? else {
                return Ok(render(error_page(state, 404, "Oops... Page Not Found!")));
            };
            p.theme = g.theme_color.clone();
            p.game = g;
            if parts.len() == 1 {
                p.kind = "game".into();
                p.title = format!("{} weapons", p.game.id);
                p.description = format!(
                    "{} weapon list. Find the best loadout for any {} gun.",
                    p.game.qualified(),
                    p.game.qualified()
                );
                let mut groups = BTreeMap::<String, Vec<Weapon>>::new();
                for w in db.weapons(game).await? {
                    groups.entry(w.kind.clone()).or_default().push(w);
                }
                p.weapons = groups
                    .into_iter()
                    .map(|(kind, weapons)| WeaponGroup { kind, weapons })
                    .collect();
                p.loadouts = db
                    .loadouts(
                        "w.game_id=? AND v.votes>0",
                        "votes DESC,l.id LIMIT 5",
                        std::slice::from_ref(game),
                    )
                    .await?;
                p.recent = db
                    .loadouts(
                        "w.game_id=?",
                        "l.updated_at DESC,l.id LIMIT 5",
                        std::slice::from_ref(game),
                    )
                    .await?;
                db.attach(&mut [&mut p.loadouts, &mut p.recent]).await?;
            } else {
                let Some(w) = db.weapon(game, &parts[1]).await? else {
                    return Ok(render(error_page(state, 404, "Oops... Page Not Found!")));
                };
                p.weapon = w;
                if parts.len() == 2 {
                    p.kind = "weapon".into();
                    p.title = format!("{} - {}", p.game.id, p.weapon.name);
                    p.description = format!(
                        "{} {} loadouts are located on this page. Find the best setup for the {} {} here.",
                        p.game.qualified(),
                        p.weapon.name,
                        p.game.qualified(),
                        p.weapon.name
                    );
                    p.loadouts = db
                        .loadouts(
                            "l.weapon_id=?",
                            "votes DESC,l.id",
                            &[p.weapon.id.to_string()],
                        )
                        .await?;
                    db.attach(&mut [&mut p.loadouts]).await?;
                    let available: Vec<Attachment>=db.rows("SELECT CAST(a.id AS SIGNED) id, CAST(0 AS SIGNED) loadout_id,a.name,COALESCE(a.slot,'') slot,COALESCE(a.image_url,'') image_url,COALESCE(a.thumb_url,'') thumb_url FROM attachment_weapon aw JOIN attachments a ON a.id=aw.attachment_id WHERE aw.weapon_id=? ORDER BY a.name,a.id",&[p.weapon.id.to_string()]).await?;
                    let mut slots = BTreeMap::<String, Vec<Attachment>>::new();
                    for a in available {
                        slots.entry(a.slot.clone()).or_default().push(a);
                    }
                    if game == "Call of Duty Advanced Warfare"
                        && let Some(other) = slots.get("Other1").cloned()
                    {
                        slots.insert("Other2".into(), other);
                    }
                    p.attachments = slots
                        .into_iter()
                        .map(|(slot, attachments)| AttachmentGroup { slot, attachments })
                        .collect();
                } else {
                    let Ok(id) = parts[2].parse::<i64>() else {
                        return Ok(render(error_page(state, 404, "Oops... Page Not Found!")));
                    };
                    let mut loads = db
                        .loadouts(
                            "l.id=? AND l.weapon_id=?",
                            "l.id",
                            &[id.to_string(), p.weapon.id.to_string()],
                        )
                        .await?;
                    if loads.is_empty() {
                        return Ok(render(error_page(state, 404, "Oops... Page Not Found!")));
                    }
                    db.attach(&mut [&mut loads]).await?;
                    p.loadout = loads.remove(0);
                    p.kind = "loadout".into();
                    p.title = format!("{} - {} - {}", p.game.id, p.weapon.name, p.loadout.pretty());
                    p.description = format!(
                        "The {} in {} with {} has {} {}.",
                        p.weapon.name,
                        p.game.qualified(),
                        p.loadout.pretty(),
                        p.loadout.votes,
                        if p.loadout.votes == 1 {
                            "vote"
                        } else {
                            "votes"
                        }
                    );
                }
            }
        }
        _ => return Ok(render(error_page(state, 404, "Oops... Page Not Found!"))),
    }
    Ok(render(p))
}
#[derive(Deserialize, Default)]
pub struct Search {
    #[serde(default)]
    query: String,
}
async fn autocomplete(
    State(state): State<Arc<AppState>>,
    Query(search): Query<Search>,
) -> Response {
    let start = Instant::now();
    let mut db = Db::new(&state.pool, state.query_timeout);
    let term = search.query.split('/').next().unwrap_or("").trim();
    let result: Result<Vec<Weapon>, _> = db
        .rows(
            &format!("SELECT {WEAPON_COLUMNS} FROM weapons WHERE name LIKE ? ORDER BY id"),
            &[format!("%{term}%")],
        )
        .await;
    let response=match result {Ok(rows)=>axum::Json(serde_json::json!({"suggestions":rows.iter().map(|w|format!("{} / {}",w.name,w.game_id)).collect::<Vec<_>>()})).into_response(),Err(_)=>(StatusCode::SERVICE_UNAVAILABLE,axum::Json(serde_json::json!({"suggestions":[],"error":"Search unavailable"}))).into_response()};
    instrument(response, &db, start, "autocomplete", state.metrics)
}
async fn search_get(
    State(state): State<Arc<AppState>>,
    Extension(session): Extension<Session>,
    Query(search): Query<Search>,
) -> Response {
    search_run(state, search, session).await
}
async fn search_post(
    State(state): State<Arc<AppState>>,
    Extension(session): Extension<Session>,
    Form(search): Form<Search>,
) -> Response {
    search_run(state, search, session).await
}
async fn search_run(state: Arc<AppState>, search: Search, session: Session) -> Response {
    let start = Instant::now();
    let mut db = Db::new(&state.pool, state.query_timeout);
    let result = search_build(&state, &mut db, &search.query, &session).await;
    let response = match result {
        Ok(r) => r,
        Err(_) => render(error_page(
            &state,
            503,
            "Search is temporarily unavailable.",
        )),
    };
    instrument(response, &db, start, "search", state.metrics)
}
async fn search_build(
    state: &AppState,
    db: &mut Db<'_>,
    query: &str,
    session: &Session,
) -> Result<Response, DbError> {
    let parts: Vec<_> = query.split('/').collect();
    if parts.len() > 1
        && let Some(w) = db.weapon(parts[1].trim(), parts[0].trim()).await?
    {
        return Ok(Redirect::to(&w.url()).into_response());
    }
    let mut p = Page::new(state);
    p.kind = "search".into();
    p.session = session.clone();
    p.title = "Search Game Loadouts".into();
    p.description = "Game Loadouts search results page.".into();
    p.query = query.into();
    let recent = db.recent().await?;
    p.nav = recent.iter().filter(|g| g.live == 1).cloned().collect();
    p.footer = recent
        .iter()
        .take(4)
        .filter(|g| g.live == 1)
        .cloned()
        .collect();
    if state.search_mode != "local" {
        if !state.search_api_url.is_empty() {
            let response = state
                .http
                .get(format!("{}{}", state.search_api_url, encode(query)))
                .send()
                .await;
            if let Ok(mut response) = response
                && response.status().is_success()
            {
                let mut bytes = Vec::new();
                while let Ok(Some(chunk)) = response.chunk().await {
                    if bytes.len() + chunk.len() > 2 * 1024 * 1024 {
                        bytes.clear();
                        break;
                    }
                    bytes.extend_from_slice(&chunk);
                }
                if let Ok(json) = serde_json::from_slice::<serde_json::Value>(&bytes)
                    && json.get("queries").is_some()
                {
                    p.results = json
                        .get("items")
                        .and_then(|v| v.as_array())
                        .into_iter()
                        .flatten()
                        .filter_map(|v| {
                            let url = v.get("link")?.as_str()?;
                            if !url.starts_with("https://") && !url.starts_with("http://") {
                                return None;
                            }
                            Some(SearchItem {
                                name: v.get("title")?.as_str()?.into(),
                                url: url.into(),
                                snippet: v
                                    .get("snippet")
                                    .and_then(|s| s.as_str())
                                    .unwrap_or("")
                                    .into(),
                            })
                        })
                        .collect();
                    return Ok(render(p));
                }
            }
        }
        // Same external Google fallback as Laravel, without an API key requirement.
        return Ok(Redirect::to(&format!(
            "https://www.google.com/?#q=site:gameloadouts.com+{}",
            encode(query)
        ))
        .into_response());
    }
    let weapons:Vec<Weapon>=db.rows(&format!("SELECT {WEAPON_COLUMNS} FROM weapons WHERE name LIKE ? OR game_id LIKE ? ORDER BY id"),&[format!("%{}%",query.trim()),format!("%{}%",query.trim())]).await?;
    p.results = weapons
        .iter()
        .map(|w| SearchItem {
            name: format!("{} / {}", w.name, w.game_id),
            url: w.url(),
            snippet: String::new(),
        })
        .collect();
    Ok(render(p))
}
fn xml(s: &str) -> String {
    s.replace('&', "&amp;")
        .replace('<', "&lt;")
        .replace('>', "&gt;")
        .replace('"', "&quot;")
        .replace('\'', "&apos;")
}
async fn sitemap(state: &AppState, db: &mut Db<'_>) -> Result<Response, DbError> {
    let games: Vec<Game> = db
        .rows(
            &format!("SELECT {GAME_COLUMNS} FROM games ORDER BY id"),
            &[],
        )
        .await?;
    let weapons: Vec<Weapon> = db
        .rows(
            &format!("SELECT {WEAPON_COLUMNS} FROM weapons ORDER BY id"),
            &[],
        )
        .await?;
    let loads:Vec<Loadout>=db.rows("SELECT CAST(l.id AS SIGNED) id,CAST(w.id AS SIGNED) weapon_id,w.game_id,w.name weapon_name,CAST(0 AS SIGNED) votes,'' author FROM loadouts l JOIN weapons w ON w.id=l.weapon_id ORDER BY l.id",&[]).await?;
    let mut out = String::from(
        "<?xml version=\"1.0\" encoding=\"UTF-8\"?><urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">",
    );
    let mut add = |path: String, priority: &str, freq: &str| {
        out.push_str(&format!("<url><loc>{}</loc><lastmod>2014-05-20T12:30:00+02:00</lastmod><changefreq>{freq}</changefreq><priority>{priority}</priority></url>",xml(&format!("{}{}",state.public_url,path))));
    };
    add("/".into(), "1.0", "daily");
    add("/games".into(), "0.8", "weekly");
    for g in games {
        add(g.url(), "0.8", "daily");
        for w in weapons.iter().filter(|w| w.game_id == g.id) {
            add(w.url(), "0.8", "weekly");
            for l in loads.iter().filter(|l| l.weapon_id == w.id) {
                add(l.url(), "0.5", "weekly");
            }
        }
    }
    out.push_str("</urlset>");
    Ok((
        [(
            axum::http::header::CONTENT_TYPE,
            "application/xml; charset=utf-8",
        )],
        out,
    )
        .into_response())
}
