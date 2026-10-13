#!/bin/bash
set -euo pipefail
mkdir -p hand-forged/test-results
cleanup() {
 docker cp hf-web:/var/www/html/app/storage/logs/laravel.log hand-forged/test-results/laravel.log >/dev/null 2>&1 || true
 docker logs hf-web > hand-forged/test-results/application.log 2>&1 || true
 docker logs hf-database > hand-forged/test-results/database.log 2>&1 || true
 docker rm -f hf-web hf-database >/dev/null 2>&1 || true
}
trap cleanup EXIT
start=$(date +%s)
export HAND_FORGED_STARTED_AT="$(python3 -c 'import time;print(time.time())')"
launch() {
 docker run -d --name hf-database --memory=256m --cpus=1 --tmpfs /var/lib/mysql:size=192m -p 127.0.0.1:8080:8080 hand-forged-db
 docker run -d --name hf-web --memory=256m --cpus=1 --network container:hf-database -e HAND_FORGED_LOCAL_TEST=true hand-forged-web
}
launch
python3 hand-forged/smoke.py
node hand-forged/browser.mjs
ready=$(date +%s)
echo "startup_and_checks_seconds=$((ready-start))" > hand-forged/test-results/measurements.txt
for name in hf-web hf-database; do docker exec "$name" sh -c 'cat /sys/fs/cgroup/memory.peak 2>/dev/null || cat /sys/fs/cgroup/memory/memory.max_usage_in_bytes' | sed "s/^/$name peak_bytes=/" >> hand-forged/test-results/measurements.txt; done
docker stats --no-stream --format '{{.Name}} {{.MemUsage}} {{.CPUPerc}}' hf-web hf-database >> hand-forged/test-results/measurements.txt
docker image inspect --format '{{.RepoTags}} {{.Size}}' hand-forged-web hand-forged-db >> hand-forged/test-results/measurements.txt
docker exec hf-web sh -c 'echo disposable > public/uploads/hand-forged-reset-marker.txt'
docker exec hf-database mysql --socket=/tmp/demo.sock -uroot -e "UPDATE games SET short_name='RESETMARKER' WHERE id='Battlefield 6'" game_loadouts_hand_forged
docker stop -t 10 hf-web hf-database
docker inspect --format '{{.Name}} exit={{.State.ExitCode}}' hf-web hf-database >> hand-forged/test-results/measurements.txt
docker logs hf-web > hand-forged/test-results/first-shutdown.log 2>&1
cleanup
launch
for i in $(seq 1 180); do curl --fail --silent http://127.0.0.1:8080/ >/dev/null && break || sleep 1; done
curl --fail --silent http://127.0.0.1:8080/ | grep -q 'Hand-Forged Edition'
docker exec hf-web test ! -e public/uploads/hand-forged-reset-marker.txt
count=$(docker exec hf-database mysql --socket=/tmp/demo.sock -uroot -NBe "SELECT COUNT(*) FROM games WHERE short_name='RESETMARKER'" game_loadouts_hand_forged)
test "$count" = 0
count=$(docker exec hf-database mysql --socket=/tmp/demo.sock -uroot -NBe 'SELECT COUNT(*) FROM users' game_loadouts_hand_forged)
test "$count" = 22
test -z "$(docker port hf-database 3306/tcp)"
echo 'PASS replaced instance restores all seed rows and uploaded-file state; MySQL port is unpublished'
