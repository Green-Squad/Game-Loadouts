use crate::model::*;
use crate::remote::{self as sqlx, Connection, Pool};
use serde::de::DeserializeOwned;
use std::time::{Duration, Instant};

pub const GAME_COLUMNS: &str = "id, CAST(live AS INTEGER) live, COALESCE(short_name,'') short_name, COALESCE(theme_color,'green') theme_color, COALESCE(thumb_url,'') thumb_url, COALESCE(header_url,'') header_url";
pub const WEAPON_COLUMNS: &str = "CAST(id AS INTEGER) id, name, game_id, COALESCE(type,'') kind, COALESCE(image_url,'') image_url, COALESCE(thumb_url,'') thumb_url, CAST(min_attachments AS INTEGER) min_attachments, CAST(max_attachments AS INTEGER) max_attachments";
// Aggregate votes BEFORE joining attachments. No attachment/vote multiplication.
pub const LOADOUT_COLUMNS: &str = "CAST(l.id AS INTEGER) id, CAST(l.weapon_id AS INTEGER) weapon_id, w.game_id, w.name weapon_name, CAST(COALESCE(v.votes,0) AS INTEGER) votes, CASE WHEN u.role = 'Guest' OR u.username IS NULL THEN 'Guest' ELSE u.username END author";
pub fn loadout_sql(predicate: &str, order: &str) -> String {
    // Scope the grouped vote work to this route's selected weapon/game/loadout.
    let vote_filter = predicate
        .replace(" AND v.votes>0", "")
        .replace("w.", "vw.")
        .replace("l.", "vl.");
    format!(
        "SELECT {LOADOUT_COLUMNS} FROM loadouts l JOIN weapons w ON w.id=l.weapon_id LEFT JOIN (SELECT lu.loadout_id, COUNT(*) votes FROM loadout_user lu JOIN loadouts vl ON vl.id=lu.loadout_id JOIN weapons vw ON vw.id=vl.weapon_id WHERE {vote_filter} GROUP BY lu.loadout_id) v ON v.loadout_id=l.id LEFT JOIN users u ON u.email=l.user_id WHERE {predicate} ORDER BY {order}"
    )
}
#[derive(Debug)]
pub enum DbError {
    Query,
    Timeout,
}
pub struct Db<'a> {
    pub pool: &'a Pool,
    pub timeout: Duration,
    connection: Option<Connection>,
    pub count: usize,
    pub millis: f64,
}
impl<'a> Db<'a> {
    pub fn new(pool: &'a Pool, timeout: Duration) -> Self {
        Self {
            pool,
            timeout,
            connection: None,
            count: 0,
            millis: 0.0,
        }
    }
    pub async fn rows<T>(&mut self, sql: &str, binds: &[String]) -> Result<Vec<T>, DbError>
    where
        T: DeserializeOwned,
    {
        let start = Instant::now();
        if self.connection.is_none() {
            self.connection = Some(
                tokio::time::timeout(self.timeout, self.pool.acquire())
                    .await
                    .map_err(|_| DbError::Timeout)?
                    .map_err(|_| DbError::Query)?,
            );
        }
        let conn = self.connection.as_ref().ok_or(DbError::Query)?;
        let mut q = sqlx::query_as::<T>(sql);
        for bind in binds {
            q = q.bind(bind);
        }
        self.count += 1;
        let result = tokio::time::timeout(self.timeout, q.fetch_all(conn)).await;
        self.millis += start.elapsed().as_secs_f64() * 1000.0;
        match result {
            Ok(Ok(rows)) => {
                if rows.len() > 10000 {
                    tracing::warn!(
                        rows = rows.len(),
                        "large public result set; returned in full"
                    );
                }
                Ok(rows)
            }
            Ok(Err(error)) => {
                // Diagnostic codes/column names only; never log SQL, bind values,
                // connect options or the database server's raw error message.
                tracing::error!(kind=?error,"SQL execution/fetch failed");
                Err(DbError::Query)
            }
            Err(_) => Err(DbError::Timeout),
        }
    }
    pub async fn recent(&mut self) -> Result<Vec<Game>, DbError> {
        self.rows(
            &format!("SELECT {GAME_COLUMNS} FROM games ORDER BY created_at DESC, id LIMIT 5"),
            &[],
        )
        .await
    }
    pub async fn game(&mut self, id: &str) -> Result<Option<Game>, DbError> {
        Ok(self
            .rows(
                &format!("SELECT {GAME_COLUMNS} FROM games WHERE id=? AND live=1"),
                &[id.into()],
            )
            .await?
            .pop())
    }
    pub async fn weapons(&mut self, game: &str) -> Result<Vec<Weapon>, DbError> {
        self.rows(
            &format!("SELECT {WEAPON_COLUMNS} FROM weapons WHERE game_id=? ORDER BY _key_type,_key_name,id"),
            &[game.into()],
        )
        .await
    }
    pub async fn weapon(&mut self, game: &str, name: &str) -> Result<Option<Weapon>, DbError> {
        Ok(self.rows(&format!("SELECT {WEAPON_COLUMNS} FROM weapons WHERE game_id=? AND name=? ORDER BY id LIMIT 1"), &[game.into(),name.into()]).await?.pop())
    }
    pub async fn loadouts(
        &mut self,
        predicate: &str,
        order: &str,
        binds: &[String],
    ) -> Result<Vec<Loadout>, DbError> {
        let doubled: Vec<String> = binds.iter().chain(binds.iter()).cloned().collect();
        self.rows(&loadout_sql(predicate, order), &doubled).await
    }

    pub async fn attach(&mut self, lists: &mut [&mut Vec<Loadout>]) -> Result<(), DbError> {
        let ids: std::collections::BTreeSet<i64> =
            lists.iter().flat_map(|l| l.iter().map(|x| x.id)).collect();
        if ids.is_empty() {
            return Ok(());
        }
        // Full weapon result sets use a relational filter instead of an unbounded IN list.
        let weapon_ids: std::collections::BTreeSet<i64> = lists
            .iter()
            .flat_map(|l| l.iter().map(|x| x.weapon_id))
            .collect();
        let (filter, binds) = if ids.len() > 1000 && weapon_ids.len() == 1 {
            (
                "al.loadout_id IN (SELECT id FROM loadouts WHERE weapon_id=?)".into(),
                vec![weapon_ids.first().unwrap().to_string()],
            )
        } else {
            (
                format!("al.loadout_id IN ({})", vec!["?"; ids.len()].join(",")),
                ids.iter().map(ToString::to_string).collect(),
            )
        };
        let rows: Vec<Attachment> = self.rows(&format!("SELECT CAST(a.id AS INTEGER) id, CAST(al.loadout_id AS INTEGER) loadout_id, a.name, COALESCE(a.slot,'') slot, COALESCE(a.image_url,'') image_url, COALESCE(a.thumb_url,'') thumb_url FROM attachment_loadout al JOIN attachments a ON a.id=al.attachment_id WHERE {filter} ORDER BY al.loadout_id,a.id"), &binds).await?;
        let mut by_id = std::collections::HashMap::<i64, Vec<Attachment>>::new();
        for row in rows {
            by_id.entry(row.loadout_id).or_default().push(row);
        }
        for list in lists {
            for l in list.iter_mut() {
                l.attachments = by_id.get(&l.id).cloned().unwrap_or_default();
            }
        }
        Ok(())
    }
}
