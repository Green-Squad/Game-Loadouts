//! Immutable Rust-owned uploads live in private Turso, never Cloud Run's temporary disk.
use crate::{
    remote::{self as sqlx, Pool},
    web::AppState,
};
use axum::{
    extract::{Path, State},
    http::{HeaderMap, HeaderValue, StatusCode, header},
    response::{IntoResponse, Response},
};
use image::{ImageFormat, ImageReader};
use serde::Deserialize;
use sha2::{Digest, Sha256};
use std::{io::Cursor, sync::Arc};
const LIMIT: usize = 8 * 1024 * 1024;
const CHUNK: usize = 512 * 1024;
#[derive(Deserialize)]
struct Metadata {
    mime: String,
    size: i64,
    digest: String,
}
pub async fn store(
    pool: &Pool,
    bytes: &[u8],
    thumb: Option<u32>,
) -> Result<(String, String), &'static str> {
    if bytes.is_empty() || bytes.len() > LIMIT {
        return Err("Images must be at most 8 MB.");
    }
    let raw = bytes.to_vec();
    let (mime, ext, small) = tokio::task::spawn_blocking(move || {
        let format = image::guess_format(&raw).map_err(|_| "Upload a PNG, JPEG or GIF image.")?;
        let (mime, ext) = match format {
            ImageFormat::Png => ("image/png", "png"),
            ImageFormat::Jpeg => ("image/jpeg", "jpg"),
            ImageFormat::Gif => ("image/gif", "gif"),
            _ => return Err("Upload a PNG, JPEG or GIF image."),
        };
        let mut reader = ImageReader::with_format(Cursor::new(&raw), format);
        let mut limits = image::Limits::default();
        limits.max_image_width = Some(4096);
        limits.max_image_height = Some(4096);
        limits.max_alloc = Some(64 * 1024 * 1024);
        reader.limits(limits);
        let image = reader
            .decode()
            .map_err(|_| "The image is invalid or too large to process.")?;
        let small = if let Some(width) = thumb {
            let height = ((u64::from(image.height()) * u64::from(width)) / u64::from(image.width()))
                .max(1) as u32;
            if height > 4096 {
                return Err("The image is too tall to generate a thumbnail.");
            }
            let thumbnail =
                image.resize_exact(width, height, image::imageops::FilterType::Triangle);
            let mut encoded = Cursor::new(Vec::new());
            thumbnail
                .write_to(&mut encoded, format)
                .map_err(|_| "The thumbnail could not be generated.")?;
            Some(encoded.into_inner())
        } else {
            None
        };
        Ok::<_, &'static str>((mime, ext, small))
    })
    .await
    .map_err(|_| "The image could not be processed.")??;
    let digest = format!("{:x}", Sha256::digest(bytes));
    let original = format!("rust-media/{digest}.{ext}");
    save(pool, &original, mime, bytes).await?;
    let thumbnail = if let (Some(width), Some(small)) = (thumb, small) {
        let path = format!("rust-media/thumb/{digest}_{width}.{ext}");
        save(pool, &path, mime, &small).await?;
        path
    } else {
        original.clone()
    };
    Ok((original, thumbnail))
}
async fn save(pool: &Pool, path: &str, mime: &str, bytes: &[u8]) -> Result<(), &'static str> {
    let c = pool
        .acquire()
        .await
        .map_err(|_| "Image storage is temporarily unavailable.")?;
    let existing: Option<i64> = sqlx::query_scalar("SELECT ready FROM _rust_media WHERE path=?")
        .bind(path)
        .fetch_optional(&c)
        .await
        .map_err(|_| "Image storage is temporarily unavailable.")?;
    if existing == Some(1) {
        return Ok(());
    }
    drop(c);
    let digest = format!("{:x}", Sha256::digest(bytes));
    let tx = pool
        .begin()
        .await
        .map_err(|_| "Image storage is temporarily unavailable.")?;
    let total: i64 = sqlx::query_scalar("SELECT COALESCE(SUM(size),0) FROM _rust_media")
        .fetch_one(&*tx)
        .await
        .map_err(|_| "Image storage is temporarily unavailable.")?;
    if total + bytes.len() as i64 > 2 * 1024 * 1024 * 1024 {
        return Err("Rust image storage has reached its 2 GB limit.");
    }
    sqlx::query("INSERT INTO _rust_media(path,mime,size,digest,ready,created_utc) VALUES(?,?,?,?,0,strftime('%Y-%m-%dT%H:%M:%f','now')) ON CONFLICT DO NOTHING").bind(path).bind(mime).bind(bytes.len() as i64).bind(&digest).execute(&*tx).await.map_err(|_|"Image storage is temporarily unavailable.")?;
    tx.commit()
        .await
        .map_err(|_| "Image storage is temporarily unavailable.")?;
    for (part, chunk) in bytes.chunks(CHUNK).enumerate() {
        sqlx::query(
            "INSERT INTO _rust_media_chunks(path,part,data) VALUES(?,?,?) ON CONFLICT DO NOTHING",
        )
        .bind(path)
        .bind(part as i64)
        .bind_blob(chunk.to_vec())
        .execute(pool)
        .await
        .map_err(|_| "Image storage is temporarily unavailable.")?;
    }
    let c = pool
        .acquire()
        .await
        .map_err(|_| "Image storage is temporarily unavailable.")?;
    let content = read(&c, path, bytes.len())
        .await
        .map_err(|_| "Image storage is temporarily unavailable.")?;
    if format!("{:x}", Sha256::digest(&content)) != digest {
        return Err("The stored image could not be validated.");
    }
    drop(c);
    sqlx::query("UPDATE _rust_media SET ready=1 WHERE path=?")
        .bind(path)
        .execute(pool)
        .await
        .map_err(|_| "Image storage is temporarily unavailable.")?;
    Ok(())
}
async fn read(c: &sqlx::Connection, path: &str, size: usize) -> Result<Vec<u8>, sqlx::Error> {
    if size > LIMIT {
        return Err(sqlx::Error::Query);
    }
    let mut bytes = Vec::with_capacity(size);
    let mut part = 0;
    while bytes.len() < size {
        let chunks = c.media_chunks(path, part).await?;
        if chunks.is_empty() {
            return Err(sqlx::Error::Missing);
        }
        for chunk in chunks {
            if chunk.len() > CHUNK || bytes.len() + chunk.len() > size {
                return Err(sqlx::Error::Query);
            }
            part += 1;
            bytes.extend(chunk);
        }
    }
    Ok(bytes)
}
pub async fn serve(
    State(state): State<Arc<AppState>>,
    Path(path): Path<String>,
    headers: HeaderMap,
) -> Response {
    // The route can address only content-hash image paths, not private records or filesystem paths.
    let name = path.strip_prefix("thumb/").unwrap_or(&path);
    let Some((stem, ext)) = name.rsplit_once('.') else {
        return StatusCode::NOT_FOUND.into_response();
    };
    let hash = stem.split('_').next().unwrap_or("");
    if hash.len() != 64
        || !hash.bytes().all(|c| c.is_ascii_hexdigit())
        || !matches!(ext, "png" | "jpg" | "gif")
        || path.contains("..")
        || path.split('/').count() > 2
    {
        return StatusCode::NOT_FOUND.into_response();
    }
    let key = format!("rust-media/{path}");
    let result=async{
        let c=state.pool.acquire().await?;
        let metadata:Option<Metadata>=sqlx::query_as("SELECT mime,CAST(size AS INTEGER) size,digest FROM _rust_media WHERE path=? AND ready=1").bind(&key).fetch_optional(&c).await?;
        let Some(metadata)=metadata else{return Ok(StatusCode::NOT_FOUND.into_response());};
        let etag=format!("\"{}\"",metadata.digest);
        let mut response=if headers.get(header::IF_NONE_MATCH).and_then(|x|x.to_str().ok())==Some(&etag){StatusCode::NOT_MODIFIED.into_response()}else{
            let data=read(&c,&key,usize::try_from(metadata.size).map_err(|_|sqlx::Error::Query)?).await?;
            if format!("{:x}",Sha256::digest(&data))!=metadata.digest{return Err(sqlx::Error::Query);}data.into_response()
        };
        response.headers_mut().insert(header::ETAG,HeaderValue::from_str(&etag).map_err(|_|sqlx::Error::Query)?);
        response.headers_mut().insert(header::CONTENT_TYPE,HeaderValue::from_str(&metadata.mime).map_err(|_|sqlx::Error::Query)?);
        response.headers_mut().insert(header::CACHE_CONTROL,HeaderValue::from_static("public, max-age=31536000, immutable"));response.headers_mut().insert(header::X_CONTENT_TYPE_OPTIONS,HeaderValue::from_static("nosniff"));Ok::<_,sqlx::Error>(response)
    }.await;
    result.unwrap_or_else(|_| StatusCode::SERVICE_UNAVAILABLE.into_response())
}
pub async fn publish_stylesheet(pool: &Pool) -> Result<(), &'static str> {
    let bytes = include_bytes!("../data/admin-styles.css");
    let digest = format!("{:x}", Sha256::digest(bytes));
    let path = format!("rust-css/{digest}.css");
    save(pool, &path, "text/css; charset=utf-8", bytes).await?;
    sqlx::query("INSERT INTO _rust_settings(key,value) VALUES('css_path',?) ON CONFLICT DO UPDATE SET value=excluded.value").bind(path).execute(pool).await.map_err(|_|"Stylesheet storage is temporarily unavailable.")?;
    Ok(())
}
pub async fn stylesheet(State(state): State<Arc<AppState>>, headers: HeaderMap) -> Response {
    let legacy = include_bytes!("../data/legacy-styles.css");
    let attempt=async{
        if state.admin.is_none(){return Ok::<_,sqlx::Error>(None);}
        let c=state.pool.acquire().await?;let path:Option<String>=sqlx::query_scalar("SELECT value FROM _rust_settings WHERE key='css_path'").fetch_optional(&c).await?;
        let Some(path)=path else{return Ok(None);};
        if !path.starts_with("rust-css/")||!path.ends_with(".css"){return Err(sqlx::Error::Query);}
        let meta:Metadata=sqlx::query_as("SELECT mime,CAST(size AS INTEGER) size,digest FROM _rust_media WHERE path=? AND ready=1").bind(&path).fetch_one(&c).await?;
        let bytes=read(&c,&path,usize::try_from(meta.size).map_err(|_|sqlx::Error::Query)?).await?;
        if format!("{:x}",Sha256::digest(&bytes))!=meta.digest{return Err(sqlx::Error::Query);}Ok(Some(bytes))
    }.await;
    let bytes = attempt.ok().flatten().unwrap_or_else(|| legacy.to_vec());
    let etag = format!("\"{:x}\"", Sha256::digest(&bytes));
    let mut response = if headers
        .get(header::IF_NONE_MATCH)
        .and_then(|x| x.to_str().ok())
        == Some(etag.as_str())
    {
        StatusCode::NOT_MODIFIED.into_response()
    } else {
        bytes.into_response()
    };
    response.headers_mut().insert(
        header::CONTENT_TYPE,
        HeaderValue::from_static("text/css; charset=utf-8"),
    );
    response.headers_mut().insert(
        header::CACHE_CONTROL,
        HeaderValue::from_static("public, max-age=3600"),
    );
    response.headers_mut().insert(
        header::ETAG,
        HeaderValue::from_str(&etag).expect("hexadecimal digest"),
    );
    response
}
