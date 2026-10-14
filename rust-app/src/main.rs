use game_loadouts::account::Accounts;
use game_loadouts::remote::Pool;
use game_loadouts::web::{AppState, app};
use std::{
    env,
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
    let pool = Pool::from_env(false).await?;
    let command = env::args().nth(1);
    if let Some(command) = command {
        if command != "--check-public-schema" {
            return Err("Unknown command".into());
        }
        game_loadouts::schema::validate(&pool).await?;
        tracing::info!("Migrated public account schema verified");
        return Ok(());
    }
    let accounts = if env::var("INTERACTIVE_ENABLED").as_deref() == Ok("1") {
        let write_pool = Pool::from_env(true).await?;
        game_loadouts::schema::validate(&write_pool).await?;
        Some(Arc::new(Accounts::from_env(write_pool)?))
    } else {
        None
    };
    pool.acquire().await?;
    let port = u16::try_from(number("PORT", 8080)?)?;
    let state = Arc::new(AppState {
        pool: pool.clone(),
        accounts,
        admin: if env::var("ADMIN_ENABLED").as_deref() == Ok("1") {
            Some(Pool::admin_from_env().await?)
        } else {
            None
        },
        ads: env::var("ADS_ENABLED").as_deref() == Ok("1"),
        analytics_id: env::var("GA4_MEASUREMENT_ID")
            .unwrap_or_default()
            .chars()
            .filter(|c| c.is_ascii_alphanumeric() || *c == '-')
            .collect(),
        query_timeout: Duration::from_millis(number("DB_QUERY_TIMEOUT_MS", 5000)?),
        public_url: env::var("PUBLIC_BASE_URL")
            .unwrap_or_else(|_| "https://gameloadouts.com".into())
            .trim_end_matches('/')
            .into(),
        media_origin: env::var("MEDIA_ORIGIN")
            .unwrap_or_default()
            .trim_end_matches('/')
            .into(),
        disqus: env::var("DISQUS_ENABLED").as_deref() != Ok("0"),
        metrics: env::var("METRICS_HEADERS").as_deref() == Ok("1"),
        search_mode: env::var("SEARCH_MODE").unwrap_or_else(|_| "google".into()),
        search_api_url: env::var("SEARCH_API_URL")
            .or_else(|_| env::var("SEARCH_URL"))
            .unwrap_or_default(),
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
