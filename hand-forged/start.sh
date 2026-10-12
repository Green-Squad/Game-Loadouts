#!/bin/sh
set -eu
[ "${HAND_FORGED_DEMO:-}" = true ] || { echo 'Demo mode is required' >&2; exit 1; }
export DB_HOST=127.0.0.1 DB_DATABASE=game_loadouts_hand_forged DB_USERNAME=handforged DB_PASSWORD=HandForgedLocalDatabaseOnly
export KEY="$(openssl rand -hex 16)"
unset RECAPTCHA_PRIVATE_KEY DISQUS_SECRET_KEY SEARCH_URL MAIL_USERNAME MAIL_PASSWORD
php hand-forged/wait_for_database.php
exec apache2-foreground
