//! Read-only validation of the existing Laravel schema. No migrations or DDL.
use sqlx::MySqlPool;
pub async fn validate(pool: &MySqlPool) -> Result<(), sqlx::Error> {
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
    for (table, required) in tables.iter().chain(std::iter::once(&reminders)) {
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
