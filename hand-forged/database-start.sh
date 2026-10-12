#!/bin/sh
set -eu
# Every new instance starts from an image-bundled, clean synthetic snapshot.
# No old volume or database connection is accepted.
rm -rf /var/lib/mysql/*
cp -a /opt/seed-database/. /var/lib/mysql/
chown -R mysql:mysql /var/lib/mysql
mysqld --user=mysql --datadir=/var/lib/mysql --skip-networking --socket=/tmp/demo.sock --pid-file=/tmp/demo.pid &
pid=$!
cleanup() { kill -TERM "$pid" 2>/dev/null || true; wait "$pid" || true; }
trap cleanup TERM INT EXIT
for i in $(seq 1 60); do mysqladmin --socket=/tmp/demo.sock ping >/dev/null 2>&1 && break || sleep 1; done
mysql --socket=/tmp/demo.sock -uroot game_loadouts_hand_forged <<'SQL'
SET @days=DATEDIFF(CURDATE(),'2020-01-31');
UPDATE games SET created_at=TIMESTAMPADD(DAY,@days,created_at),updated_at=TIMESTAMPADD(DAY,@days,updated_at);
UPDATE weapons SET created_at=TIMESTAMPADD(DAY,@days,created_at),updated_at=TIMESTAMPADD(DAY,@days,updated_at);
UPDATE attachments SET created_at=TIMESTAMPADD(DAY,@days,created_at),updated_at=TIMESTAMPADD(DAY,@days,updated_at);
UPDATE loadouts SET created_at=TIMESTAMPADD(DAY,@days,created_at),updated_at=TIMESTAMPADD(DAY,@days,updated_at);
UPDATE loadout_user SET created_at=TIMESTAMPADD(DAY,@days,created_at),updated_at=TIMESTAMPADD(DAY,@days,updated_at);
UPDATE users SET disabled_until=NOW()-INTERVAL 1 HOUR,created_at=NOW(),updated_at=NOW();
SQL
mysqladmin --socket=/tmp/demo.sock -uroot shutdown
wait "$pid"
trap - TERM INT EXIT
# Cloud Run exposes only the web container's port; this sidecar port is private.
exec gosu mysql mysqld --datadir=/var/lib/mysql --bind-address=0.0.0.0 --port=3306 --socket=/tmp/demo.sock --pid-file=/tmp/demo.pid
