//! Explicit operator migration and schema checks. The web server never runs DDL.
use sqlx::MySqlPool;
const MIGRATION: &str = include_str!("../migrations/001_public_accounts.sql");
pub async fn validate(pool: &MySqlPool, include_rust: bool) -> Result<(), sqlx::Error> {
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
    let rust_tables = [
        (
            "rust_sessions",
            vec![
                "token_hash",
                "user_email",
                "csrf",
                "expires_at",
                "created_at",
                "credential_hash",
            ],
        ),
        (
            "rust_account_tokens",
            vec![
                "user_email",
                "purpose",
                "token_hash",
                "created_at",
                "expires_at",
            ],
        ),
    ];
    for (table, required) in tables
        .iter()
        .chain(rust_tables.iter().filter(|_| include_rust))
    {
        let engine:Option<String>=sqlx::query_scalar("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?").bind(table).fetch_optional(pool).await?;
        if engine.as_deref() != Some("InnoDB") {
            tracing::error!(table, "Public writes require an existing InnoDB table");
            return Err(sqlx::Error::Protocol("Schema preflight failed".into()));
        }
        let columns:Vec<String>=sqlx::query_scalar("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?").bind(table).fetch_all(pool).await?;
        if required
            .iter()
            .any(|column| !columns.iter().any(|found| found == column))
        {
            tracing::error!(table, "Schema is missing public account columns");
            return Err(sqlx::Error::Protocol("Schema preflight failed".into()));
        }
    }
    Ok(())
}
pub async fn migrate(pool: &MySqlPool) -> Result<(), sqlx::Error> {
    validate(pool, false).await?;
    sqlx::raw_sql(MIGRATION).execute(pool).await?;
    validate(pool, true).await?;
    tracing::info!("Additive public account migration verified");
    Ok(())
}
