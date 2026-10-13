//! Read-only validation of the existing Laravel schema. No migrations or DDL.
use crate::remote::{self as sqlx, Pool};
use serde::Deserialize;
pub async fn validate(pool: &Pool) -> Result<(), sqlx::Error> {
    let tables = [
        (
            "users",
            vec![
                "email",
                "username",
                "password",
                "role",
                "converted_guest",
                "remember_token",
                "disabled_until",
                "failed_attempts",
                "confirm_token",
                "created_at",
                "updated_at",
            ],
        ),
        (
            "weapons",
            vec![
                "id",
                "game_id",
                "name",
                "min_attachments",
                "max_attachments",
            ],
        ),
        (
            "loadouts",
            vec!["id", "weapon_id", "user_id", "created_at", "updated_at"],
        ),
        (
            "loadout_user",
            vec!["loadout_id", "user_id", "created_at", "updated_at"],
        ),
        (
            "attachment_loadout",
            vec!["attachment_id", "loadout_id", "created_at", "updated_at"],
        ),
        ("attachment_weapon", vec!["attachment_id", "weapon_id"]),
        ("attachments", vec!["id", "slot"]),
    ];
    let reminders = ("password_reminders", vec!["email", "token", "created_at"]);
    let conn = pool.acquire().await?;
    #[derive(Deserialize)]
    struct Column {
        name: String,
    }
    for (table, required) in tables.iter().chain(std::iter::once(&reminders)) {
        let columns: Vec<Column> = sqlx::query_as(&format!(
            "PRAGMA table_info(_g{}_{table})",
            conn.generation()
        ))
        .fetch_all(&conn)
        .await?;
        if required
            .iter()
            .any(|name| !columns.iter().any(|c| c.name == *name))
        {
            tracing::error!(table, "Migrated schema is missing public account columns");
            return Err(sqlx::Error::Configuration);
        }
    }
    Ok(())
}
