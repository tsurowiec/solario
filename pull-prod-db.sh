#!/usr/bin/env bash
# Replace the local SQLite database with a snapshot of production.
set -euo pipefail

REMOTE_HOST=ksse
REMOTE_DB=/home/forge/solario.orkana8.net/storage/database.sqlite
REMOTE_TMP=/tmp/solario-prod-$$.sqlite
LOCAL_DB=database/database.sqlite

cd "$(dirname "$0")"

# Consistent snapshot on the server (safe while the app is writing)
ssh "$REMOTE_HOST" "sqlite3 '$REMOTE_DB' \".backup '$REMOTE_TMP'\""
trap 'ssh "$REMOTE_HOST" "rm -f \"$REMOTE_TMP\""' EXIT

if [ -f "$LOCAL_DB" ]; then
    backup="database/database.$(date +%Y%m%d-%H%M%S).sqlite"
    cp "$LOCAL_DB" "$backup"
    echo "Local DB backed up to $backup"
fi

rm -f "$LOCAL_DB-wal" "$LOCAL_DB-shm"
scp -q "$REMOTE_HOST:$REMOTE_TMP" "$LOCAL_DB"
echo "Production DB copied to $LOCAL_DB"

php artisan migrate
