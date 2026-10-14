//! A small parameterized SQL adapter over the official remote libSQL client.
//! No local database files or source database connections exist in this layer.
use libsql::{Builder, Database, TransactionBehavior, Value as SqlValue};
use regex::Regex;
use serde::{Deserialize, Serialize, de::DeserializeOwned};
use serde_json::{Map, Value};
use sha2::{Digest, Sha256};
use std::{
    collections::BTreeMap,
    env,
    ops::{Deref, DerefMut},
    sync::{Arc, OnceLock},
    time::Duration,
};
use tokio::sync::{OwnedSemaphorePermit, Semaphore};

#[derive(Debug, Clone, Copy)]
pub enum Error {
    Query,
    Timeout,
    Unique,
    Missing,
    Configuration,
}
impl std::fmt::Display for Error {
    fn fmt(&self, f: &mut std::fmt::Formatter<'_>) -> std::fmt::Result {
        f.write_str("Remote database operation failed")
    }
}
impl std::error::Error for Error {}
impl From<libsql::Error> for Error {
    fn from(e: libsql::Error) -> Self {
        #[cfg(feature = "fixture-tests")]
        if env::var("TURSO_TEST_DATABASE").as_deref() == Ok("isolated") {
            // Synthetic fixture diagnostics only; never log values or raw SQL.
            match &e {
                libsql::Error::RemoteSqliteFailure(code, extended, message) => {
                    let category = if message.contains("syntax") {
                        "syntax"
                    } else if message.contains("no such column") {
                        "column"
                    } else if message.contains("no such table") {
                        "table"
                    } else if message.contains("parameters") {
                        "parameters"
                    } else if message.contains("locked") {
                        "locked"
                    } else {
                        "other"
                    };
                    eprintln!(
                        "fixture database failure code={code} extended={extended} category={category}"
                    );
                }
                _ => eprintln!(
                    "fixture database failure variant={:?}",
                    std::mem::discriminant(&e)
                ),
            }
        }
        match e {
            libsql::Error::RemoteSqliteFailure(_, 1555 | 2067, _)
            | libsql::Error::SqliteFailure(1555 | 2067, _) => Self::Unique,
            _ => Self::Query,
        }
    }
}

pub fn unicode_key(text: &str) -> Result<Vec<u8>, Error> {
    unicode_key_exact(text.trim_end_matches(' '))
}

fn unicode_key_exact(text: &str) -> Result<Vec<u8>, Error> {
    static WEIGHTS: OnceLock<BTreeMap<u32, Vec<u16>>> = OnceLock::new();
    let weights = WEIGHTS.get_or_init(|| {
        serde_json::from_str(include_str!("../data/uca4-primary.json"))
            .expect("bundled Unicode 4 primary weights")
    });
    let mut out = Vec::new();
    for ch in text.chars() {
        let cp = u32::from(ch);
        if cp > 0xffff {
            return Err(Error::Query);
        }
        if let Some(ws) = weights.get(&cp) {
            for w in ws {
                out.extend(w.to_be_bytes());
            }
        } else {
            let base = if (0x4e00..=0x9fff).contains(&cp) {
                0xfb40
            } else if (0x3400..=0x4dbf).contains(&cp) {
                0xfb80
            } else {
                0xfbc0
            };
            out.extend(((base + (cp >> 15)) as u16).to_be_bytes());
            out.extend(((cp & 0x7fff) as u16 | 0x8000).to_be_bytes());
        }
    }
    Ok(out)
}

/// MySQL LIKE compares characters individually, including accent/case weights;
/// unlike equality it neither trims spaces nor expands one character into two.
fn legacy_like(text: &str, pattern: &str) -> Result<bool, Error> {
    let chars: Vec<_> = text
        .chars()
        .map(|c| unicode_key_exact(&c.to_string()))
        .collect::<Result<_, _>>()?;
    let mut tokens = Vec::new();
    let mut iter = pattern.chars();
    while let Some(c) = iter.next() {
        tokens.push(match c {
            '%' => (0, Vec::new()),
            '_' => (1, Vec::new()),
            '\\' => (
                2,
                unicode_key_exact(&iter.next().unwrap_or('\\').to_string())?,
            ),
            _ => (2, unicode_key_exact(&c.to_string())?),
        });
    }
    let mut previous = vec![false; chars.len() + 1];
    previous[0] = true;
    for (kind, weight) in tokens {
        let mut next = vec![false; chars.len() + 1];
        if kind == 0 {
            next[0] = previous[0];
        }
        for i in 1..=chars.len() {
            next[i] = if kind == 0 {
                previous[i] || next[i - 1]
            } else {
                previous[i - 1] && (kind == 1 || chars[i - 1] == weight)
            };
        }
        previous = next;
    }
    Ok(previous[chars.len()])
}

#[derive(Clone)]
pub struct Pool {
    database: Arc<Database>,
    permits: Arc<Semaphore>,
    timeout: Duration,
    writer: bool,
}

pub struct Connection {
    inner: libsql::Connection,
    transaction: Option<libsql::Transaction>,
    generation: i64,
    timeout: Duration,
    writer: bool,
    _permit: OwnedSemaphorePermit,
}

pub struct WriteTransaction {
    connection: Connection,
}
impl Deref for WriteTransaction {
    type Target = Connection;
    fn deref(&self) -> &Connection {
        &self.connection
    }
}
impl DerefMut for WriteTransaction {
    fn deref_mut(&mut self) -> &mut Connection {
        &mut self.connection
    }
}

impl Pool {
    pub async fn from_env(writer: bool) -> Result<Self, Error> {
        Self::configured(
            writer,
            if writer {
                "TURSO_WRITE_TOKEN"
            } else {
                "TURSO_AUTH_TOKEN"
            },
        )
        .await
    }
    pub async fn admin_from_env() -> Result<Self, Error> {
        Self::configured(true, "TURSO_ADMIN_TOKEN").await
    }
    async fn configured(writer: bool, token_name: &str) -> Result<Self, Error> {
        let url = env::var("TURSO_DATABASE_URL").map_err(|_| Error::Configuration)?;
        let token = env::var(token_name).unwrap_or_default();
        let timeout = Duration::from_millis(
            env::var("DB_QUERY_TIMEOUT_MS")
                .unwrap_or_else(|_| "5000".into())
                .parse()
                .map_err(|_| Error::Configuration)?,
        );
        let max = env::var("DB_POOL_MAX")
            .unwrap_or_else(|_| "10".into())
            .parse::<usize>()
            .map_err(|_| Error::Configuration)?;
        let isolated = env::var("TURSO_TEST_DATABASE").as_deref() == Ok("isolated")
            && (url.starts_with("http://127.0.0.1:")
                || url.starts_with("http://localhost:")
                || url.starts_with("http://fixture-public:")
                || url.starts_with("http://fixture-accounts:"));
        if !(1..=100).contains(&max)
            || timeout.is_zero()
            || (!url.starts_with("libsql://") && !url.starts_with("https://") && !isolated)
        {
            return Err(Error::Configuration);
        }
        let db = tokio::time::timeout(
            timeout,
            Builder::new_remote(url, token.trim().to_owned()).build(),
        )
        .await
        .map_err(|_| Error::Timeout)??;
        Ok(Self {
            database: Arc::new(db),
            permits: Arc::new(Semaphore::new(max)),
            timeout,
            writer,
        })
    }

    #[cfg(feature = "fixture-tests")]
    pub async fn connect_isolated(url: &str, writer: bool) -> Result<Self, Error> {
        let parsed = reqwest::Url::parse(url).map_err(|_| Error::Configuration)?;
        if env::var("TURSO_TEST_DATABASE").as_deref() != Ok("isolated")
            || parsed.scheme() != "http"
            || !matches!(
                parsed.host_str(),
                Some("127.0.0.1" | "localhost" | "fixture-public" | "fixture-accounts")
            )
        {
            return Err(Error::Configuration);
        }
        let database = Builder::new_remote(url.to_owned(), String::new())
            .build()
            .await?;
        Ok(Self {
            database: Arc::new(database),
            permits: Arc::new(Semaphore::new(12)),
            timeout: Duration::from_secs(5),
            writer,
        })
    }

    async fn open(&self, write: bool) -> Result<Connection, Error> {
        if write && !self.writer {
            return Err(Error::Configuration);
        }
        tokio::time::timeout(self.timeout, async {
            let permit = self
                .permits
                .clone()
                .acquire_owned()
                .await
                .map_err(|_| Error::Query)?;
            let inner = self.database.connect()?;
            let transaction = inner
                .transaction_with_behavior(if write {
                    TransactionBehavior::Immediate
                } else {
                    TransactionBehavior::ReadOnly
                })
                .await?;
            // Remote transactions own a separate Hrana stream. Every query and
            // write must use that stream, including the generation pin and epoch.
            let inner = (*transaction).clone();
            if write {
                inner
                    .execute_batch("ROLLBACK; PRAGMA foreign_keys=ON; BEGIN IMMEDIATE;")
                    .await?;
            }
            let mut rows = inner
                .query(
                    "SELECT generation,syncing FROM _migration_state WHERE id=1",
                    (),
                )
                .await?;
            let state = rows.next().await?.ok_or(Error::Missing)?;
            let generation: i64 = state.get(0)?;
            let syncing: i64 = state.get(1)?;
            if generation <= 0 || (write && syncing != 0) {
                return Err(Error::Timeout);
            }
            Ok(Connection {
                inner,
                transaction: Some(transaction),
                generation,
                timeout: self.timeout,
                writer: write,
                _permit: permit,
            })
        })
        .await
        .map_err(|_| Error::Timeout)?
    }

    pub async fn acquire(&self) -> Result<Connection, Error> {
        self.open(false).await
    }
    pub async fn begin(&self) -> Result<WriteTransaction, Error> {
        Ok(WriteTransaction {
            connection: self.open(true).await?,
        })
    }
    pub async fn close(&self) {}
}

impl WriteTransaction {
    pub async fn commit(mut self) -> Result<(), Error> {
        tokio::time::timeout(self.connection.timeout, async {
            self.connection
                .inner
                .execute("UPDATE _rust_write_epoch SET value=value+1 WHERE id=1", ())
                .await?;
            self.connection
                .transaction
                .take()
                .ok_or(Error::Missing)?
                .commit()
                .await?;
            Ok(())
        })
        .await
        .map_err(|_| Error::Timeout)?
    }
}

pub enum Handle<'a> {
    Borrowed(&'a Connection),
    Owned(Connection),
}
impl Deref for Handle<'_> {
    type Target = Connection;
    fn deref(&self) -> &Connection {
        match self {
            Self::Borrowed(c) => c,
            Self::Owned(c) => c,
        }
    }
}
pub trait Executor {
    fn acquire(
        &self,
        write: bool,
    ) -> impl std::future::Future<Output = Result<Handle<'_>, Error>> + Send;
}
impl Executor for Connection {
    async fn acquire(&self, write: bool) -> Result<Handle<'_>, Error> {
        if write && !self.writer {
            return Err(Error::Configuration);
        }
        Ok(Handle::Borrowed(self))
    }
}
impl Executor for Pool {
    async fn acquire(&self, write: bool) -> Result<Handle<'_>, Error> {
        Ok(Handle::Owned(self.open(write).await?))
    }
}

fn table_names(sql: &str, generation: i64) -> String {
    static TABLES: OnceLock<Regex> = OnceLock::new();
    TABLES.get_or_init(|| Regex::new(r"(?i)\b(FROM|JOIN|INTO|UPDATE)\s+(users|games|weapons|attachments|loadouts|loadout_user|attachment_loadout|attachment_weapon|password_reminders)\b").expect("static table matcher"))
        .replace_all(sql, |c: &regex::Captures<'_>| format!("{} _g{}_{}", &c[1], generation, &c[2])).into_owned()
}

fn key_column(column: &str, games: bool) -> Option<String> {
    let (prefix, name) = column
        .rsplit_once('.')
        .map_or(("", column), |(a, b)| (a, b));
    let keyed = matches!(
        name,
        "email"
            | "username"
            | "password"
            | "confirm_token"
            | "token"
            | "game_id"
            | "user_id"
            | "name"
            | "role"
            | "slot"
            | "type"
            | "short_name"
            | "theme_color"
            | "image_url"
            | "thumb_url"
            | "header_url"
    ) || (name == "id" && (prefix == "g" || (prefix.is_empty() && games)));
    keyed.then(|| {
        if prefix.is_empty() {
            format!("_key_{name}")
        } else {
            format!("{prefix}._key_{name}")
        }
    })
}

fn comparisons(sql: &str, args: &[SqlValue]) -> Result<(String, Vec<SqlValue>), Error> {
    static LITERALS: OnceLock<Regex> = OnceLock::new();
    static PARAMS: OnceLock<Regex> = OnceLock::new();
    static JOINS: OnceLock<Regex> = OnceLock::new();
    let games = sql.contains("FROM games ")
        || sql.starts_with("UPDATE games ")
        || sql.starts_with("DELETE FROM games ");
    let start = if sql.starts_with("UPDATE ") {
        sql.find(" WHERE ").ok_or(Error::Query)?
    } else if sql.starts_with("INSERT ") {
        sql.len()
    } else {
        0
    };
    let prefix = &sql[..start];
    let body = &sql[start..];
    let mut params = args.to_vec();
    let mut last = 0;
    let mut index = prefix.matches('?').count();
    let mut failure = false;
    let mut out = String::from(prefix);
    let matcher = PARAMS.get_or_init(|| {
        Regex::new(r"\b([A-Za-z_][A-Za-z_0-9]*(?:\.[A-Za-z_][A-Za-z_0-9]*)?)\s*=\s*\?")
            .expect("static comparison matcher")
    });
    for c in matcher.captures_iter(body) {
        let m = c.get(0).expect("match");
        index += body[last..m.start()].matches('?').count();
        out.push_str(&body[last..m.start()]);
        if let Some(col) = key_column(&c[1], games) {
            out.push_str(&format!("{col}=?"));
            match params.get_mut(index) {
                Some(SqlValue::Text(s)) => match unicode_key(s) {
                    Ok(key) => params[index] = SqlValue::Blob(key),
                    Err(_) => failure = true,
                },
                Some(SqlValue::Null) => {}
                _ => failure = true,
            }
        } else {
            out.push_str(m.as_str());
        }
        index += 1;
        last = m.end();
    }
    out.push_str(&body[last..]);
    if failure {
        return Err(Error::Query);
    }
    let joins = JOINS.get_or_init(|| Regex::new(r"\b([A-Za-z_][A-Za-z_0-9]*\.(?:email|game_id|user_id|id))\s*=\s*([A-Za-z_][A-Za-z_0-9]*\.(?:email|game_id|user_id|id))\b").expect("static join matcher"));
    out = joins
        .replace_all(&out, |c: &regex::Captures<'_>| {
            match (key_column(&c[1], games), key_column(&c[2], games)) {
                (Some(a), Some(b)) => format!("{a}={b}"),
                _ => c[0].to_owned(),
            }
        })
        .into_owned();
    let literals = LITERALS.get_or_init(|| {
        Regex::new(r"\b([A-Za-z_][A-Za-z_0-9]*(?:\.[A-Za-z_][A-Za-z_0-9]*)?)\s*(=|<>)\s*'([^']*)'")
            .expect("static literal matcher")
    });
    let literal_start = if sql.starts_with("UPDATE ") {
        out.find(" WHERE ").ok_or(Error::Query)?
    } else if sql.starts_with("INSERT ") {
        out.len()
    } else {
        0
    };
    let raw_prefix = out[..literal_start].to_owned();
    out = raw_prefix
        + literals
            .replace_all(&out[literal_start..], |c: &regex::Captures<'_>| {
                let name = c[1].rsplit('.').next().unwrap_or("");
                if matches!(name, "role" | "confirm_token") {
                    let column = if let Some((alias, _)) = c[1].rsplit_once('.') {
                        format!("{alias}._key_{name}")
                    } else {
                        format!("_key_{name}")
                    };
                    if let Ok(key) = unicode_key(&c[3]) {
                        return format!(
                            "{column}{}X'{}'",
                            &c[2],
                            key.iter().map(|b| format!("{b:02x}")).collect::<String>()
                        );
                    }
                }
                c[0].to_owned()
            })
            .as_ref();
    Ok((out, params))
}

fn split_expressions(text: &str) -> Vec<&str> {
    let mut depth = 0;
    let mut quoted = false;
    let mut start = 0;
    let mut out = Vec::new();
    for (i, ch) in text.char_indices() {
        if ch == '\'' {
            quoted = !quoted;
        }
        if !quoted {
            match ch {
                '(' => depth += 1,
                ')' => depth -= 1,
                ',' if depth == 0 => {
                    out.push(text[start..i].trim());
                    start = i + 1;
                }
                _ => {}
            }
        }
    }
    out.push(text[start..].trim());
    out
}

fn expression_value(
    expr: &str,
    args: &[SqlValue],
    index: &mut usize,
) -> Result<Option<SqlValue>, Error> {
    let value = if expr == "?" {
        Some(args.get(*index).ok_or(Error::Query)?.clone())
    } else if expr.starts_with('\'') && expr.ends_with('\'') {
        Some(SqlValue::Text(expr[1..expr.len() - 1].replace("''", "'")))
    } else {
        None
    };
    *index += expr.matches('?').count();
    Ok(value)
}

fn extra_key(column: &str, value: &SqlValue) -> Result<Option<(String, SqlValue)>, Error> {
    if !matches!(
        column,
        "email"
            | "username"
            | "password"
            | "role"
            | "remember_token"
            | "confirm_token"
            | "token"
            | "game_id"
            | "user_id"
            | "name"
            | "id"
            | "slot"
            | "type"
            | "short_name"
            | "theme_color"
            | "image_url"
            | "thumb_url"
            | "header_url"
    ) {
        return Ok(None);
    }
    if column == "id" && !matches!(value, SqlValue::Text(_)) {
        return Ok(None);
    }
    let key = match value {
        SqlValue::Text(s) => SqlValue::Blob(unicode_key(s)?),
        SqlValue::Null => SqlValue::Null,
        _ => return Err(Error::Query),
    };
    Ok(Some((format!("_key_{column}"), key)))
}

impl Connection {
    pub async fn insert_rows(
        &self,
        table: &str,
        columns: &[&str],
        rows: &[Vec<SqlValue>],
    ) -> Result<Vec<i64>, Error> {
        tokio::time::timeout(self.timeout, self.insert_rows_inner(table, columns, rows))
            .await
            .map_err(|_| Error::Timeout)?
    }
    async fn insert_rows_inner(
        &self,
        table: &str,
        columns: &[&str],
        rows: &[Vec<SqlValue>],
    ) -> Result<Vec<i64>, Error> {
        if !self.writer
            || !matches!(table, "weapons" | "attachments" | "attachment_weapon")
            || rows.len() > 50
            || rows.iter().any(|r| r.len() != columns.len())
        {
            return Err(Error::Configuration);
        }
        if rows.is_empty() {
            return Ok(vec![]);
        }
        let mut has_id = table != "attachment_weapon";
        if table == "attachment_weapon" {
            let mut info = self
                .inner
                .query(
                    &format!("PRAGMA table_info(_g{}_{table})", self.generation),
                    (),
                )
                .await?;
            while let Some(row) = info.next().await? {
                has_id |= row.get::<String>(1)? == "id";
            }
        }
        let first = if has_id {
            let mut result=self.inner.query("UPDATE _rust_sequences SET next_id=next_id+? WHERE table_name=? RETURNING next_id-?",(rows.len() as i64,table,rows.len() as i64)).await?;
            result.next().await?.ok_or(Error::Missing)?.get::<i64>(0)?
        } else {
            0
        };
        let mut names: Vec<String> = columns.iter().map(|x| (*x).into()).collect();
        let extras: Vec<_> = columns
            .iter()
            .enumerate()
            .filter(|(_, name)| {
                matches!(
                    **name,
                    "name" | "slot" | "type" | "game_id" | "image_url" | "thumb_url"
                )
            })
            .map(|(i, name)| (i, format!("_key_{name}")))
            .collect();
        names.extend(extras.iter().map(|(_, n)| n.clone()));
        if has_id {
            names.push("id".into());
        }
        let mut args = Vec::new();
        let mut values = Vec::new();
        for (index, row) in rows.iter().enumerate() {
            args.extend(row.iter().cloned());
            for (column, _) in &extras {
                args.push(match &row[*column] {
                    SqlValue::Text(v) => SqlValue::Blob(unicode_key(v)?),
                    SqlValue::Null => SqlValue::Null,
                    _ => return Err(Error::Query),
                });
            }
            if has_id {
                args.push(SqlValue::Integer(first + index as i64));
            }
            values.push(format!("({})", vec!["?"; names.len()].join(",")));
        }
        let sql = format!(
            "INSERT INTO _g{}_{table} ({}) VALUES {}",
            self.generation,
            names.join(","),
            values.join(",")
        );
        tokio::time::timeout(self.timeout, self.inner.execute(&sql, args))
            .await
            .map_err(|_| Error::Timeout)??;
        Ok(if has_id {
            (first..first + rows.len() as i64).collect()
        } else {
            vec![]
        })
    }
    pub async fn media_chunks(&self, path: &str, offset: i64) -> Result<Vec<Vec<u8>>, Error> {
        tokio::time::timeout(self.timeout,async{
            let mut rows=self.inner.query("SELECT data FROM _rust_media_chunks WHERE path=? AND part>=? ORDER BY part LIMIT 2",(path,offset)).await?;let mut out=Vec::new();
            while let Some(row)=rows.next().await?{out.push(row.get::<Vec<u8>>(0)?);}Ok(out)
        }).await.map_err(|_|Error::Timeout)?
    }
    pub fn generation(&self) -> i64 {
        self.generation
    }
    pub async fn rows<T: DeserializeOwned>(
        &self,
        sql: &str,
        args: &[SqlValue],
        scalar: bool,
    ) -> Result<Vec<T>, Error> {
        let mut like = Vec::new();
        let mut selected = sql.to_owned();
        for (suffix, columns) in [
            (
                " WHERE name LIKE ? OR game_id LIKE ? ORDER BY id",
                vec!["name", "game_id"],
            ),
            (" WHERE name LIKE ? ORDER BY id", vec!["name"]),
        ] {
            if selected.ends_with(suffix) && args.len() == columns.len() {
                for (column, arg) in columns.into_iter().zip(args) {
                    like.push((column, arg.as_text().ok_or(Error::Query)?.clone()));
                }
                selected = selected.replace(suffix, " ORDER BY id");
                break;
            }
        }
        let (sql, args) = comparisons(&selected, if like.is_empty() { args } else { &[] })?;
        tokio::time::timeout(self.timeout, async {
            let mut rows = self
                .inner
                .query(&table_names(&sql, self.generation), args)
                .await?;
            let mut out = Vec::new();
            while let Some(row) = rows.next().await? {
                let mut object = Map::new();
                let mut array = Vec::new();
                for i in 0..row.column_count() {
                    let value = match row.get_value(i)? {
                        SqlValue::Null => Value::Null,
                        SqlValue::Integer(n) => Value::from(n),
                        SqlValue::Real(n) => Value::from(n),
                        SqlValue::Text(s) => Value::String(s),
                        SqlValue::Blob(_) => return Err(Error::Query),
                    };
                    object.insert(
                        row.column_name(i).ok_or(Error::Missing)?.to_owned(),
                        value.clone(),
                    );
                    array.push(value);
                }
                if !like.is_empty() {
                    let mut matched = false;
                    for (column, pattern) in &like {
                        if let Some(Value::String(text)) = object.get(*column) {
                            matched |= legacy_like(text, pattern)?;
                        }
                    }
                    if !matched {
                        continue;
                    }
                }
                let value = if scalar {
                    array.first().cloned().ok_or(Error::Missing)?
                } else {
                    Value::Object(object)
                };
                let item = serde_json::from_value(value)
                    .or_else(|_| {
                        if scalar {
                            Err(serde_json::Error::io(std::io::Error::other(
                                "scalar decode",
                            )))
                        } else {
                            serde_json::from_value(Value::Array(array))
                        }
                    })
                    .map_err(|_| Error::Query)?;
                out.push(item);
            }
            Ok(out)
        })
        .await
        .map_err(|_| Error::Timeout)?
    }

    async fn execute(&self, sql: &str, original: &[SqlValue]) -> Result<Outcome, Error> {
        tokio::time::timeout(self.timeout, self.execute_inner(sql, original))
            .await
            .map_err(|_| Error::Timeout)?
    }
    async fn execute_inner(&self, sql: &str, original: &[SqlValue]) -> Result<Outcome, Error> {
        if !self.writer {
            return Err(Error::Configuration);
        }
        let (mut sql, mut args) = comparisons(sql, original)?;
        if sql.starts_with("INSERT INTO ") {
            let open = sql.find('(').ok_or(Error::Query)?;
            let end = sql[open..].find(')').ok_or(Error::Query)? + open;
            let table = sql[12..open].trim().to_owned();
            let values_start = sql
                .find(" VALUES(")
                .map(|i| i + 8)
                .or_else(|| sql.find(" VALUES (").map(|i| i + 9))
                .ok_or(Error::Query)?;
            let values_end = sql.rfind(')').ok_or(Error::Query)?;
            let columns = split_expressions(&sql[open + 1..end]);
            let expressions = split_expressions(&sql[values_start..values_end]);
            if columns.len() != expressions.len() {
                return Err(Error::Query);
            }
            let mut values = BTreeMap::new();
            let mut index = 0;
            for (col, expr) in columns.iter().zip(expressions) {
                if let Some(v) = expression_value(expr, original, &mut index)? {
                    values.insert((*col).to_owned(), v);
                }
            }
            let mut extras = Vec::new();
            for (col, v) in &values {
                if let Some(extra) = extra_key(col, v)? {
                    extras.push(extra);
                }
            }
            if table == "users" {
                let password = values
                    .get("password")
                    .and_then(|v| v.as_text().map(String::as_str))
                    .ok_or(Error::Query)?;
                let remember = values
                    .get("remember_token")
                    .and_then(|v| v.as_text().map(String::as_str))
                    .unwrap_or("");
                extras.push((
                    "_credential_hash".into(),
                    SqlValue::Text(format!(
                        "{:x}",
                        Sha256::digest(format!("{password}\0{remember}").as_bytes())
                    )),
                ));
            }
            if matches!(
                table.as_str(),
                "loadouts"
                    | "loadout_user"
                    | "attachment_loadout"
                    | "weapons"
                    | "attachments"
                    | "attachment_weapon"
            ) && !columns.contains(&"id")
            {
                let has_id = if table == "attachment_weapon" {
                    let mut rows = self
                        .inner
                        .query(
                            &format!("PRAGMA table_info(_g{}_{table})", self.generation),
                            (),
                        )
                        .await?;
                    let mut found = false;
                    while let Some(row) = rows.next().await? {
                        found |= row.get::<String>(1)? == "id";
                    }
                    found
                } else {
                    true
                };
                if has_id {
                    let mut result=self.inner.query("UPDATE _rust_sequences SET next_id=next_id+1 WHERE table_name=? RETURNING next_id-1",[table.clone()]).await?;
                    let id: i64 = result.next().await?.ok_or(Error::Missing)?.get(0)?;
                    extras.push(("id".into(), SqlValue::Integer(id)));
                }
            }
            let mut new_columns = sql[open + 1..end].to_owned();
            let mut new_expressions = sql[values_start..values_end].to_owned();
            for (col, v) in extras {
                new_columns.push(',');
                new_columns.push_str(&col);
                new_expressions.push_str(",?");
                args.push(v);
            }
            sql = format!(
                "{}({new_columns}) VALUES({new_expressions}){}",
                &sql[..open],
                &sql[values_end + 1..]
            );
        } else if sql.starts_with("UPDATE ") {
            let set = sql.find(" SET ").ok_or(Error::Query)?;
            let where_at = sql.find(" WHERE ").ok_or(Error::Query)?;
            let table = sql[7..set].trim().to_owned();
            let assignments = split_expressions(&sql[set + 5..where_at]);
            let mut changes = BTreeMap::new();
            let mut count = 0;
            for assignment in assignments {
                let (col, expr) = assignment.split_once('=').ok_or(Error::Query)?;
                if let Some(v) = expression_value(expr.trim(), original, &mut count)? {
                    changes.insert(col.trim().to_owned(), v);
                }
            }
            let mut extra = Vec::new();
            for (col, v) in &changes {
                if let Some(key) = extra_key(col, v)? {
                    extra.push(key);
                }
            }
            if table == "users"
                && (changes.contains_key("password") || changes.contains_key("remember_token"))
            {
                let query = format!(
                    "SELECT password,remember_token FROM _g{}_users{}",
                    self.generation,
                    &sql[where_at..]
                );
                let mut rows = self.inner.query(&query, args[count..].to_vec()).await?;
                let Some(row) = rows.next().await? else {
                    return Ok(Outcome { affected: 0, id: 0 });
                };
                let old_password: String = row.get(0)?;
                let old_remember: String = row.get(1)?;
                let password = changes
                    .get("password")
                    .and_then(|v| v.as_text().map(String::as_str))
                    .unwrap_or(&old_password);
                let remember = changes
                    .get("remember_token")
                    .and_then(|v| v.as_text().map(String::as_str))
                    .unwrap_or(&old_remember);
                extra.push((
                    "_credential_hash".into(),
                    SqlValue::Text(format!(
                        "{:x}",
                        Sha256::digest(format!("{password}\0{remember}").as_bytes())
                    )),
                ));
            }
            let mut suffix = String::new();
            let mut values = Vec::new();
            for (col, v) in extra {
                suffix.push_str(&format!(",{col}=?"));
                values.push(v);
            }
            args.splice(count..count, values);
            sql.insert_str(where_at, &suffix);
        }
        let sql = table_names(&sql, self.generation);
        tokio::time::timeout(self.timeout, async {
            let affected = self.inner.execute(&sql, args).await?;
            Ok(Outcome {
                affected,
                id: self.inner.last_insert_rowid(),
            })
        })
        .await
        .map_err(|_| Error::Timeout)?
    }
}

pub struct Outcome {
    affected: u64,
    id: i64,
}
impl Outcome {
    pub fn rows_affected(&self) -> u64 {
        self.affected
    }
    pub fn last_insert_id(&self) -> i64 {
        self.id
    }
}

#[derive(Debug)]
pub struct Row {
    object: Map<String, Value>,
}
impl<'de> Deserialize<'de> for Row {
    fn deserialize<D: serde::Deserializer<'de>>(d: D) -> Result<Self, D::Error> {
        Ok(Self {
            object: Map::deserialize(d)?,
        })
    }
}
impl Row {
    pub fn try_get<T: DeserializeOwned, N: AsRef<str>>(&self, name: N) -> Result<T, Error> {
        serde_json::from_value(
            self.object
                .get(name.as_ref())
                .ok_or(Error::Missing)?
                .clone(),
        )
        .map_err(|_| Error::Query)
    }
}

pub struct Query<T> {
    sql: String,
    args: Vec<SqlValue>,
    scalar: bool,
    error: bool,
    _output: std::marker::PhantomData<T>,
}
pub fn query(sql: &str) -> Query<Row> {
    Query::new(sql, false)
}
pub fn query_as<T>(sql: &str) -> Query<T> {
    Query::new(sql, false)
}
pub fn query_scalar<T>(sql: &str) -> Query<T> {
    Query::new(sql, true)
}
impl<T> Query<T> {
    fn new(sql: &str, scalar: bool) -> Self {
        Self {
            sql: sql.into(),
            args: Vec::new(),
            scalar,
            error: false,
            _output: std::marker::PhantomData,
        }
    }
    pub fn bind_blob(mut self, value: Vec<u8>) -> Self {
        self.args.push(SqlValue::Blob(value));
        self
    }
    pub fn bind<V: Serialize>(mut self, v: V) -> Self {
        let value = serde_json::to_value(v).ok().and_then(|v| match v {
            Value::Null => Some(SqlValue::Null),
            Value::String(s) => Some(SqlValue::Text(s)),
            Value::Number(n) => n.as_i64().map(SqlValue::Integer),
            Value::Bool(b) => Some(SqlValue::Integer(i64::from(b))),
            _ => None,
        });
        if let Some(value) = value {
            self.args.push(value);
        } else {
            self.error = true;
        }
        self
    }
    pub async fn execute(self, executor: &impl Executor) -> Result<Outcome, Error> {
        if self.error {
            return Err(Error::Query);
        }
        let conn = executor.acquire(true).await?;
        tokio::time::timeout(conn.timeout, async {
            let result = conn
                .execute(&self.sql, &self.args)
                .await
                .inspect_err(|_e| {
                    #[cfg(feature = "fixture-tests")]
                    eprintln!(
                        "fixture execute signature={:x} kind={_e:?}",
                        Sha256::digest(self.sql.as_bytes())
                    );
                })?;
            if let Handle::Owned(mut conn) = conn {
                conn.inner
                    .execute("UPDATE _rust_write_epoch SET value=value+1 WHERE id=1", ())
                    .await?;
                conn.transaction
                    .take()
                    .ok_or(Error::Missing)?
                    .commit()
                    .await?;
            }
            Ok(result)
        })
        .await
        .map_err(|_| Error::Timeout)?
    }
}
impl<T: DeserializeOwned> Query<T> {
    pub async fn fetch_all(self, executor: &impl Executor) -> Result<Vec<T>, Error> {
        if self.error {
            return Err(Error::Query);
        }
        executor
            .acquire(false)
            .await?
            .rows(&self.sql, &self.args, self.scalar)
            .await
            .inspect_err(|_e| {
                #[cfg(feature = "fixture-tests")]
                eprintln!(
                    "fixture fetch signature={:x} kind={_e:?}",
                    Sha256::digest(self.sql.as_bytes())
                );
            })
    }
    pub async fn fetch_optional(self, executor: &impl Executor) -> Result<Option<T>, Error> {
        Ok(self.fetch_all(executor).await?.into_iter().next())
    }
    pub async fn fetch_one(self, executor: &impl Executor) -> Result<T, Error> {
        self.fetch_optional(executor).await?.ok_or(Error::Missing)
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn legacy_like_preserves_mariadb_character_semantics() {
        for (text, pattern, expected) in [
            ("ß", "ss", false),
            ("ß", "s", false),
            ("é", "e", true),
            ("e\u{301}", "e", false),
            ("A", "a", true),
            ("é", "_", true),
            ("Å", "a", true),
            ("™", "tm", false),
            ("a ", "a", false),
            ("Pokémon", "%MON", true),
            ("a%b", r"a\%b", true),
        ] {
            assert_eq!(
                legacy_like(text, pattern).unwrap(),
                expected,
                "{text} LIKE {pattern}"
            );
        }
    }
    #[test]
    fn unicode_matches_legacy_comparisons() {
        for (a, b) in [
            ("A", "a"),
            ("é", "e\u{301}"),
            ("ß", "ss"),
            ("Å", "A "),
            ("™", "tM"),
        ] {
            assert_eq!(unicode_key(a).unwrap(), unicode_key(b).unwrap());
        }
        assert_ne!(unicode_key("æ").unwrap(), unicode_key("ae").unwrap());
        assert_ne!(unicode_key("İ").unwrap(), unicode_key("ı").unwrap());
    }
    #[test]
    fn bound_comparisons_preserve_raw_update_values_and_binary_tokens() {
        let (sql, args) = comparisons(
            "UPDATE users SET password=? WHERE email=? AND password=?",
            &[
                SqlValue::Text("hash".into()),
                SqlValue::Text("A@example.invalid".into()),
                SqlValue::Text("old".into()),
            ],
        )
        .unwrap();
        assert_eq!(
            sql,
            "UPDATE users SET password=? WHERE _key_email=? AND _key_password=?"
        );
        assert!(matches!(&args[0], SqlValue::Text(_)));
        assert!(matches!(&args[1], SqlValue::Blob(_)));
        let (sql, args) = comparisons(
            "SELECT email FROM password_reminders WHERE email=? AND token COLLATE BINARY=?",
            &[SqlValue::Text("a".into()), SqlValue::Text("CaSe".into())],
        )
        .unwrap();
        assert!(sql.contains("token COLLATE BINARY=?"));
        assert!(matches!(&args[1], SqlValue::Text(_)));
    }
}
