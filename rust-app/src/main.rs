use game_loadouts::web::{AppState, app};
use sqlx::ConnectOptions;
use sqlx::mysql::{MySqlConnectOptions, MySqlPoolOptions, MySqlSslMode};
use std::{
    env,
    str::FromStr,
    sync::Arc,
    time::{Duration, Instant},
};
fn number(name: &str, default: u64) -> Result<u64, &'static str> {
    match env::var(name) {
        Ok(v) => v.parse().map_err(|_| "Invalid numeric configuration"),
        Err(_) => Ok(default),
    }
}
#[tokio::main]
async fn main() {
    tracing_subscriber::fmt()
        .json()
        .with_env_filter(
            tracing_subscriber::EnvFilter::try_from_default_env()
                .unwrap_or_else(|_| "game_loadouts=info".into()),
        )
        .init();
    if run().await.is_err() {
        tracing::error!(
            "Startup failed; check database connectivity and configuration. Credentials and error details are intentionally withheld."
        );
        std::process::exit(1);
    }
}
async fn run() -> Result<(), Box<dyn std::error::Error>> {
    let start = Instant::now();
    let mut options = if let Ok(url) = env::var("DATABASE_URL") {
        MySqlConnectOptions::from_str(&url).map_err(|_| "Invalid DATABASE_URL")?
    } else {
        MySqlConnectOptions::new()
            .host(&env::var("DB_HOST").unwrap_or_else(|_| "127.0.0.1".into()))
            .port(u16::try_from(number("DB_PORT", 3306)?)?)
            .database(&env::var("DB_DATABASE").map_err(|_| "DB_DATABASE required")?)
            .username(&env::var("DB_USERNAME").map_err(|_| "DB_USERNAME required")?)
    };
    if env::var("DATABASE_URL").is_err()
        && let Ok(password) = env::var("DB_PASSWORD")
        && !password.is_empty()
    {
        options = options.password(&password);
    }
    if let Ok(socket) = env::var("DB_SOCKET") {
        options = options.socket(socket);
    }
    if let Ok(mode) = env::var("DB_SSL_MODE") {
        options =
            options.ssl_mode(MySqlSslMode::from_str(&mode).map_err(|_| "Invalid DB_SSL_MODE")?);
    }
    if let Ok(ca) = env::var("DB_SSL_CA") {
        options = options.ssl_ca(ca);
    }
    let connect_timeout = Duration::from_millis(number("DB_CONNECT_TIMEOUT_MS", 5000)?);
    let max = number("DB_POOL_MAX", 10)?;
    if !(1..=100).contains(&max) {
        return Err("DB_POOL_MAX must be 1..100".into());
    }
    // No migrations, cache, warmup queries or write endpoints. Session-level guard
    // complements SELECT-only privileges; no user data is changed.
    let pool = MySqlPoolOptions::new()
        .max_connections(max as u32)
        .min_connections(0)
        .acquire_timeout(Duration::from_millis(number(
            "DB_ACQUIRE_TIMEOUT_MS",
            5000,
        )?))
        .idle_timeout(Duration::from_secs(300))
        .max_lifetime(Duration::from_secs(1800))
        .after_connect(|c, _| {
            Box::pin(async move {
                sqlx::query("SET SESSION TRANSACTION READ ONLY")
                    .execute(c)
                    .await?;
                Ok(())
            })
        })
        .connect_lazy_with(options.disable_statement_logging());
    tokio::time::timeout(connect_timeout, pool.acquire())
        .await
        .map_err(|_| "DB connect timeout")??;
    let port = u16::try_from(number("PORT", 8080)?)?;
    let state = Arc::new(AppState {
        pool: pool.clone(),
        query_timeout: Duration::from_millis(number("DB_QUERY_TIMEOUT_MS", 5000)?),
        public_url: env::var("PUBLIC_BASE_URL")
            .unwrap_or_else(|_| "https://gameloadouts.com".into())
            .trim_end_matches('/')
            .into(),
        media_origin: env::var("MEDIA_ORIGIN")
            .unwrap_or_default()
            .trim_end_matches('/')
            .into(),
        disqus: env::var("DISQUS_ENABLED").as_deref() == Ok("1"),
        metrics: env::var("METRICS_HEADERS").as_deref() == Ok("1"),
        search_mode: env::var("SEARCH_MODE").unwrap_or_else(|_| "google".into()),
        search_api_url: env::var("SEARCH_API_URL").unwrap_or_default(),
        http: reqwest::Client::builder()
            .timeout(Duration::from_secs(5))
            .connect_timeout(Duration::from_secs(2))
            .build()?,
    });
    let listener = tokio::net::TcpListener::bind((std::net::Ipv4Addr::UNSPECIFIED, port)).await?;
    tracing::info!(
        port,
        startup_ms = start.elapsed().as_secs_f64() * 1000.0,
        "ready"
    );
    axum::serve(
        listener,
        app(
            state,
            &env::var("ASSET_DIR").unwrap_or_else(|_| "public".into()),
        ),
    )
    .with_graceful_shutdown(shutdown())
    .await?;
    pool.close().await;
    Ok(())
}
async fn shutdown() {
    let ctrl_c = async {
        let _ = tokio::signal::ctrl_c().await;
    };
    #[cfg(unix)]
    let terminate = async {
        if let Ok(mut s) = tokio::signal::unix::signal(tokio::signal::unix::SignalKind::terminate())
        {
            s.recv().await;
        }
    };
    #[cfg(not(unix))]
    let terminate = std::future::pending::<()>();
    tokio::select! {_=ctrl_c=>{},_=terminate=>{}}
    tracing::info!("shutdown requested");
}
