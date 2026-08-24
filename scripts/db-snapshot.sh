#!/usr/bin/env bash
#
# Take a VERIFIED snapshot of this install's database before any write.
#
# This is a production instance. Every DB modification — a data fix, a
# migration, a seeder run — is preceded by a snapshot, and the snapshot is
# proven restorable before the write proceeds. A dump that exists but does not
# restore is not a backup.
#
#   ./scripts/db-snapshot.sh            take + verify a snapshot
#   ./scripts/db-snapshot.sh --restore /var/backups/atlas-db/<file>.sql.gz
#
# NEVER run migrate:fresh, db:wipe, or db:seed --fresh against this database.
# The seeder here is idempotent (firstOrCreate) and safe to re-run; "fresh"
# variants are not, and there is no undo.
set -euo pipefail

SNAPDIR=${SNAPDIR:-/var/backups/atlas-db}
APPDIR=$(cd "$(dirname "$0")/.." && pwd)
cd "$APPDIR"

CNF=$(mktemp); chmod 600 "$CNF"
trap 'rm -f "$CNF"' EXIT

# Credentials come from the booted app, not by parsing .env — .env values may
# be quoted or contain characters a naive parse mangles.
CNF="$CNF" php artisan tinker --execute='
$d = config("database.connections.".config("database.default"));
file_put_contents(getenv("CNF"), "[client]\nhost=".$d["host"]."\nport=".$d["port"]."\nuser=".$d["username"]."\npassword=\"".$d["password"]."\"\n");
' >/dev/null 2>&1

DB=$(CNF="$CNF" php artisan tinker --execute='echo config("database.connections.".config("database.default").".database");' 2>/dev/null | tr -d '\n')
[ -n "$DB" ] || { echo "could not resolve database name" >&2; exit 1; }

if [ "${1:-}" = "--restore" ]; then
  SRC=${2:?usage: --restore <snapshot.sql.gz>}
  echo "About to OVERWRITE database '$DB' from $SRC"
  read -r -p "Type the database name to confirm: " confirm
  [ "$confirm" = "$DB" ] || { echo "aborted"; exit 1; }
  zcat "$SRC" | mysql --defaults-extra-file="$CNF" "$DB"
  echo "restored $DB from $SRC"
  exit 0
fi

mkdir -p "$SNAPDIR" 2>/dev/null || sudo mkdir -p "$SNAPDIR"
OUT="$SNAPDIR/${DB}-$(date +%Y%m%d-%H%M%S).sql.gz"

mysqldump --defaults-extra-file="$CNF" --single-transaction --routines --triggers "$DB" \
  | gzip > /tmp/db-snapshot.$$.gz
mv /tmp/db-snapshot.$$.gz "$OUT" 2>/dev/null || sudo mv /tmp/db-snapshot.$$.gz "$OUT"

gzip -t "$OUT"

# Verification is a real restore into a scratch database, compared row for row
# against production. Anything less does not prove the file is usable.
VERIFY="${DB//-/_}_snapshot_verify"
mysql --defaults-extra-file="$CNF" -e "DROP DATABASE IF EXISTS \`$VERIFY\`; CREATE DATABASE \`$VERIFY\`;"
zcat "$OUT" | mysql --defaults-extra-file="$CNF" "$VERIFY"

status=0
while read -r t; do
  [ -n "$t" ] || continue
  a=$(mysql --defaults-extra-file="$CNF" -N -B -e "SELECT COUNT(*) FROM \`$DB\`.\`$t\`")
  b=$(mysql --defaults-extra-file="$CNF" -N -B -e "SELECT COUNT(*) FROM \`$VERIFY\`.\`$t\`")
  if [ "$a" != "$b" ]; then
    printf '  MISMATCH %-28s prod=%s snapshot=%s\n' "$t" "$a" "$b"
    status=1
  fi
done < <(mysql --defaults-extra-file="$CNF" -N -B -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB' AND table_type='BASE TABLE'")

mysql --defaults-extra-file="$CNF" -e "DROP DATABASE \`$VERIFY\`;"

if [ "$status" -ne 0 ]; then
  echo "SNAPSHOT FAILED VERIFICATION — do not proceed with the write" >&2
  exit 1
fi

echo "verified snapshot: $OUT"
echo "restore with: $0 --restore $OUT"
