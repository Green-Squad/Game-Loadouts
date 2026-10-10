use axum::{body::Body, http::Request};
use game_loadouts::{
    model::encode,
    web::{AppState, app},
};
use http_body_util::BodyExt;
use sqlx::mysql::MySqlPoolOptions;
use std::{sync::Arc, time::Duration};
use tower::ServiceExt;

async fn fixture() -> (axum::Router, sqlx::MySqlPool) {
    let url = std::env::var("TEST_DATABASE_URL")
        .expect("Set TEST_DATABASE_URL to the isolated loadouts_fixture database");
    assert!(
        url.contains("/loadouts_fixture"),
        "Only the fixture database may be tested"
    );
    assert!(
        url.contains("@127.0.0.1:") || url.contains("@localhost:"),
        "Tests require loopback database"
    );
    let pool = MySqlPoolOptions::new()
        .max_connections(4)
        .connect(&url)
        .await
        .unwrap();
    let state = Arc::new(AppState {
        pool: MySqlPoolOptions::new()
            .max_connections(4)
            .after_connect(|c, _| {
                Box::pin(async move {
                    sqlx::query("SET SESSION TRANSACTION READ ONLY")
                        .execute(c)
                        .await?;
                    Ok(())
                })
            })
            .connect(&url.replace("root@", "loadouts_reader@"))
            .await
            .unwrap(),
        query_timeout: Duration::from_secs(2),
        public_url: "https://gameloadouts.com".into(),
        media_origin: String::new(),
        disqus: true,
        metrics: true,
        search_mode: "local".into(),
        search_api_url: String::new(),
        http: reqwest::Client::new(),
    });
    (app(state, "../public"), pool)
}
async fn request(app: &axum::Router, path: &str, method: &str, body: &str) -> (u16, usize, String) {
    let response = app
        .clone()
        .oneshot(
            Request::builder()
                .uri(path)
                .method(method)
                .header("content-type", "application/x-www-form-urlencoded")
                .body(Body::from(body.to_owned()))
                .unwrap(),
        )
        .await
        .unwrap();
    let status = response.status().as_u16();
    let queries = response
        .headers()
        .get("x-db-queries")
        .map_or(0, |v| v.to_str().unwrap().parse().unwrap());
    let text = String::from_utf8(
        response
            .into_body()
            .collect()
            .await
            .unwrap()
            .to_bytes()
            .to_vec(),
    )
    .unwrap();
    (status, queries, text)
}
#[tokio::test]
#[ignore = "requires explicitly isolated fixture MySQL/MariaDB"]
async fn public_correctness_and_query_budgets() {
    let (app, pool) = fixture().await;
    let (s, q, home) = request(&app, "/", "GET", "").await;
    assert_eq!(s, 200);
    assert_eq!(q, 4);
    assert_eq!(home.matches("class=\"panel-title\"").count(), 15);
    assert!(!home.contains("Hidden New Game"));
    assert!(home.contains("Scope &#60;script&#62;") || home.contains("Scope &lt;script&gt;"));
    assert!(!home.contains("upvoteGuest"));
    assert!(!home.contains("recaptcha"));
    assert!(!home.contains("method=\"post\""));
    let (s, q, html) = request(&app, "/Call+of+Duty+Modern+Warfare/M4A1", "GET", "").await;
    assert_eq!(s, 200);
    assert_eq!(q, 6);
    assert_eq!(html.matches("data-loadout-id=").count(), 7);
    let ids: Vec<_> = (1..=7)
        .map(|i| html.find(&format!("data-loadout-id=\"{i}\"")).unwrap())
        .collect();
    assert!(ids.windows(2).all(|w| w[0] < w[1]));
    assert!(html.contains("data-votes=\"3\""));
    assert!(html.contains("data-votes=\"0\""));
    assert_eq!(
        html.matches("Scope &#60;script&#62;").count()
            + html.matches("Scope &lt;script&gt;").count(),
        16
    ); // alt + text: 7 loadouts and available list
    let (s, q, html) = request(&app, "/Call+of+Duty+Modern+Warfare/M4A1/1", "GET", "").await;
    assert_eq!((s, q), (200, 5));
    assert!(html.contains("loadout-1"));
    assert!(html.contains("tryharddev.disqus.com/embed.js"));
    assert!(html.contains("3 votes."));
    assert!(html.contains("Fixture author"));
    assert!(!html.contains("Fixture author <script>"));
    assert!(
        request(&app, "/Call+of+Duty+Modern+Warfare/M4A1/2", "GET", "")
            .await
            .2
            .contains("Originally submitted by Guest")
    );
    for path in [
        "/Hidden+New+Game",
        "/Call+of+Duty+Modern+Warfare/Missing",
        "/Call+of+Duty+Modern+Warfare/M4A1/9999",
        "/Call+of+Duty+Modern+Warfare/Empty+Weapon/1",
        "/admin",
        "/login",
        "/Missing",
    ] {
        assert_eq!(request(&app, path, "GET", "").await.0, 404, "{path}");
    }
    for method in ["POST", "PUT", "DELETE"] {
        assert_eq!(
            request(&app, "/Call+of+Duty+Modern+Warfare/M4A1", method, "")
                .await
                .0,
            405
        );
        assert_eq!(
            request(
                &app,
                "/Call+of+Duty+Modern+Warfare/M4A1/1/upvote",
                method,
                ""
            )
            .await
            .0,
            405
        );
    }
    let special = format!(
        "/{}/{}",
        encode("A+B / Pokémon's ~"),
        encode("A+B / 50% ~ 銃")
    );
    assert_eq!(request(&app, &special, "GET", "").await.0, 200);
    assert_eq!(
        request(&app, "/Call%20of%20Duty%20Modern%20Warfare/M4A1", "GET", "")
            .await
            .0,
        200
    );
    assert_eq!(request(&app, "/%FF", "GET", "").await.0, 400);
    let (s, q, _) = request(&app, "/Call+of+Duty+Modern+Warfare/Empty+Weapon", "GET", "").await;
    assert_eq!((s, q), (200, 5));
    let (s, q, html) = request(&app, "/Call+of+Duty+Modern+Warfare", "GET", "").await;
    assert_eq!((s, q), (200, 6));
    assert!(html.contains("Recent Loadouts"));
    sqlx::query("UPDATE loadout_user SET created_at=CURRENT_DATE WHERE loadout_id=1")
        .execute(&pool)
        .await
        .unwrap();
    let (s, q, html) = request(&app, "/stats", "GET", "").await;
    assert_eq!((s, q), (200, 3));
    assert!(
        html.replace("\n", "")
            .contains("<td>Call of Duty Modern Warfare</td><td>11</td><td>7</td>")
    );
    assert!(!html.contains("<td>No Votes</td>"));
    let chart = html
        .split("id=\"chart-data\">")
        .nth(1)
        .unwrap()
        .split("</script>")
        .next()
        .unwrap();
    let chart: serde_json::Value = serde_json::from_str(chart).unwrap();
    assert!(!chart["labels"].as_array().unwrap().is_empty());
    assert!(chart["datasets"].as_array().unwrap().iter().any(|d| {
        d["label"] == "Call of Duty Modern Warfare"
            && d["data"]
                .as_array()
                .unwrap()
                .iter()
                .map(|n| n.as_i64().unwrap())
                .sum::<i64>()
                >= 3
    }));
    let (s, q, xml) = request(&app, "/sitemap.xml", "GET", "").await;
    assert_eq!((s, q), (200, 3));
    assert!(xml.contains("A%2BB+%2F+Pok%C3%A9mon%27s+%7E"));
    assert!(!xml.contains("/login"));
    assert!(xml.contains("/M4A1/7"));
    assert_eq!(request(&app, "/terms", "GET", "").await.0, 200);
    assert_eq!(request(&app, "/games", "GET", "").await.1, 1);
    let (s, q, json) = request(&app, "/search_weapons?query=M4", "GET", "").await;
    assert_eq!((s, q), (200, 1));
    assert_eq!(
        serde_json::from_str::<serde_json::Value>(&json).unwrap()["suggestions"][0],
        "M4A1 / Call of Duty Modern Warfare"
    );
    let (s, q, _) = request(
        &app,
        "/search?query=M4A1%20%2F%20Call%20of%20Duty%20Modern%20Warfare",
        "GET",
        "",
    )
    .await;
    assert_eq!((s, q), (303, 1));
    let (s, q, _) = request(
        &app,
        "/search",
        "POST",
        "query=M4A1+%2F+Call+of+Duty+Modern+Warfare",
    )
    .await;
    assert_eq!((s, q), (303, 1));
    let (s, q, html) = request(&app, "/search?query=M4", "GET", "").await;
    assert_eq!((s, q), (200, 2));
    assert!(html.contains("/Call+of+Duty+Modern+Warfare/M4A1"));
    // Constant query budget after growing the COMPLETE weapon list: test-side writes only.
    sqlx::query("INSERT INTO loadouts(id,weapon_id,user_id,created_at,updated_at) SELECT 10000+n,1,'guest@example.invalid',NOW(),NOW() FROM (SELECT a.n*100+b.n*10+c.n n FROM (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) b CROSS JOIN (SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) c) numbers").execute(&pool).await.unwrap();
    let (s, q, html) = request(&app, "/Call+of+Duty+Modern+Warfare/M4A1", "GET", "").await;
    let cleanup = sqlx::query("DELETE FROM loadouts WHERE id>=10000")
        .execute(&pool)
        .await;
    cleanup.unwrap();
    assert_eq!((s, q), (200, 6));
    assert_eq!(html.matches("data-loadout-id=").count(), 1007);
    let reader = MySqlPoolOptions::new()
        .max_connections(1)
        .connect(&url_for_reader())
        .await
        .unwrap();
    assert!(
        sqlx::query("DELETE FROM loadouts WHERE 1=0")
            .execute(&reader)
            .await
            .is_err()
    );
    let mut db = game_loadouts::db::Db::new(&reader, Duration::from_millis(5));
    assert!(matches!(
        db.rows::<(i64,)>("SELECT CAST(SLEEP(0.05) AS SIGNED)", &[])
            .await,
        Err(game_loadouts::db::DbError::Timeout)
    ));
    db.timeout = Duration::from_secs(2);
    assert_eq!(
        db.rows::<(i64,)>("SELECT CAST(1 AS SIGNED)", &[])
            .await
            .unwrap(),
        vec![(1,)]
    );
    reader.close().await;
    pool.close().await;
}

fn url_for_reader() -> String {
    std::env::var("TEST_DATABASE_URL")
        .unwrap()
        .replace("root@", "loadouts_reader@")
}

#[tokio::test]
#[ignore = "requires explicitly isolated fixture MySQL/MariaDB"]
async fn google_search_fallback_and_provider() {
    let url = url_for_reader();
    let pool = MySqlPoolOptions::new()
        .max_connections(2)
        .connect(&url)
        .await
        .unwrap();
    let make_state = |api: String| {
        Arc::new(AppState {
            pool: pool.clone(),
            query_timeout: Duration::from_secs(2),
            public_url: "https://gameloadouts.com".into(),
            media_origin: String::new(),
            disqus: false,
            metrics: true,
            search_mode: "google".into(),
            search_api_url: api,
            http: reqwest::Client::builder()
                .no_proxy()
                .timeout(Duration::from_secs(2))
                .build()
                .unwrap(),
        })
    };
    let router = app(make_state(String::new()), "../public");
    let response = router
        .oneshot(
            Request::builder()
                .uri("/search?query=A%2BB")
                .body(Body::empty())
                .unwrap(),
        )
        .await
        .unwrap();
    assert_eq!(response.status(), 303);
    assert_eq!(
        response.headers()["location"],
        "https://www.google.com/?#q=site:gameloadouts.com+A%2BB"
    );
    let listener = tokio::net::TcpListener::bind("127.0.0.1:0").await.unwrap();
    let address = listener.local_addr().unwrap();
    let provider=axum::Router::new().route("/provider",axum::routing::get(||async{axum::Json(serde_json::json!({"queries":{"request":[{"searchTerms":"fixture","totalResults":"1"}]},"items":[{"link":"https://gameloadouts.com/games","title":"Title <script>","snippet":"A safe snippet"}]}))}));
    let task = tokio::spawn(async move {
        axum::serve(listener, provider).await.unwrap();
    });
    let router = app(
        make_state(format!("http://{address}/provider?q=")),
        "../public",
    );
    let (s, q, html) = request(&router, "/search?query=fixture", "GET", "").await;
    task.abort();
    assert_eq!((s, q), (200, 1));
    assert!(html.contains("A safe snippet"));
    assert!(!html.contains("Title <script>"));
    assert!(html.contains("https://gameloadouts.com/games"));
    pool.close().await;
}
