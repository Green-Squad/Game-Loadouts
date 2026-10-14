"""Rust-only admin/upload metadata; never opens a source database."""
def initialize(db):
    db.batch([
        ("CREATE TABLE IF NOT EXISTS _rust_settings(key TEXT PRIMARY KEY,value TEXT NOT NULL)",()),
        ("CREATE TABLE IF NOT EXISTS _rust_media(path TEXT PRIMARY KEY,mime TEXT NOT NULL,size INTEGER NOT NULL CHECK(size>=0 AND size<=8388608),digest TEXT NOT NULL,ready INTEGER NOT NULL CHECK(ready IN(0,1)),created_utc TEXT NOT NULL)",()),
        ("CREATE TABLE IF NOT EXISTS _rust_media_chunks(path TEXT NOT NULL REFERENCES _rust_media(path) ON DELETE CASCADE,part INTEGER NOT NULL CHECK(part>=0),data BLOB NOT NULL CHECK(length(data)<=524288),PRIMARY KEY(path,part))",()),
        *[("INSERT INTO _rust_sequences(table_name,next_id) VALUES(?,4294967296) ON CONFLICT DO NOTHING",(t,)) for t in ("weapons","attachments","attachment_weapon")],
    ])
    generation=db.query("SELECT generation FROM _migration_state WHERE id=1")[0]["generation"]
    if not isinstance(generation,int) or generation<1:raise ValueError("Invalid Rust generation")
    db.batch([
        (f"CREATE INDEX IF NOT EXISTS _g{generation}_attachments_rust_admin_order ON _g{generation}_attachments(_key_game_id,_key_name,id)",()),
        (f"CREATE INDEX IF NOT EXISTS _g{generation}_weapons_rust_admin_order ON _g{generation}_weapons(_key_game_id,_key_type,_key_name,id)",()),
        (f"CREATE INDEX IF NOT EXISTS _g{generation}_users_rust_admin_role ON _g{generation}_users(_key_role,_key_email)",()),
    ])
