#!/usr/bin/env bash
# Export the local WordPress database into a dump that imports cleanly into
# Railway's MySQL 8. MariaDB 11 defaults to utf8mb4_uca1400_* collations that
# MySQL 8 does not recognise, so we normalise them on the way out.
#
# Usage: bin/export-db.sh [output-file]
#   default output: migration/wordpress-dump.sql
set -euo pipefail
cd "$(dirname "$0")/.."

OUT="${1:-migration/wordpress-dump.sql}"
mkdir -p "$(dirname "$OUT")"

echo "==> Dumping local database (service: db)..."
docker compose exec -T db mariadb-dump \
  -uroot -prootpass \
  --single-transaction --quick \
  --default-character-set=utf8mb4 \
  --no-tablespaces \
  wordpress > "$OUT.raw"

echo "==> Normalising collations for MySQL 8 compatibility..."
# Map MariaDB-only collations to a MySQL 8 equivalent, and promote any
# utf8mb3 charset to utf8mb4. Specific collation first, then the catch-all.
sed -E \
  -e 's/utf8mb4_uca1400_ai_ci/utf8mb4_unicode_ci/g' \
  -e 's/utf8mb4_uca1400[a-z0-9_]*/utf8mb4_unicode_ci/g' \
  -e 's/CHARSET=utf8mb3/CHARSET=utf8mb4/g' \
  -e 's/ utf8mb3/ utf8mb4/g' \
  "$OUT.raw" > "$OUT"
rm -f "$OUT.raw"

BYTES=$(wc -c < "$OUT" | tr -d ' ')
echo "==> Done: $OUT (${BYTES} bytes)"
echo
echo "Next: import into Railway's MySQL using the PUBLIC connection details"
echo "      (MySQL service -> Connect -> Public Network), e.g.:"
echo
echo "  mysql --host <PROXY_HOST> --port <PROXY_PORT> \\"
echo "        -u root -p<MYSQL_ROOT_PASSWORD> railway < $OUT"
