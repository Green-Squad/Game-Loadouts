#!/bin/sh
set -eu
mkdir -p /opt/seed-database
chown mysql:mysql /opt/seed-database
mysqld --initialize-insecure --user=mysql --datadir=/opt/seed-database
mysqld --user=mysql --datadir=/opt/seed-database --skip-networking --socket=/tmp/seed.sock --pid-file=/tmp/seed.pid &
pid=$!
trap 'kill -TERM "$pid" 2>/dev/null || true' EXIT
for i in $(seq 1 60); do mysqladmin --socket=/tmp/seed.sock ping >/dev/null 2>&1 && break || sleep 1; done
gzip -dc /opt/hand-forged-seed.sql.gz | mysql --socket=/tmp/seed.sock -uroot
mysqladmin --socket=/tmp/seed.sock -uroot shutdown
wait "$pid"
trap - EXIT
rm -f /opt/seed-database/auto.cnf /opt/seed-database/*.pid /opt/seed-database/*.sock
