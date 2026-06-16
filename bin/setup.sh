#!/usr/bin/env bash
# One-shot local setup: bring up Docker, install WordPress, activate the plugin,
# create the host pages and seed demo questions. Safe to re-run (idempotent-ish).
set -euo pipefail

cd "$(dirname "$0")/.."

WP_URL="${WP_URL:-http://localhost:8080}"
WP_TITLE="${WP_TITLE:-NAASE SE Basics Challenge}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASS="${WP_ADMIN_PASS:-admin123}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@example.com}"

wp() { docker compose exec -T wpcli wp --path=/var/www/html "$@"; }

echo "==> Starting containers..."
docker compose up -d

echo "==> Waiting for WordPress files to be ready..."
for i in $(seq 1 60); do
  if docker compose exec -T wpcli test -f /var/www/html/wp-load.php 2>/dev/null; then
    break
  fi
  sleep 2
done

echo "==> Waiting for the database..."
for i in $(seq 1 60); do
  if wp db check >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

if wp core is-installed >/dev/null 2>&1; then
  echo "==> WordPress already installed."
else
  echo "==> Installing WordPress..."
  wp core install \
    --url="$WP_URL" \
    --title="$WP_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASS" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email
fi

echo "==> Setting pretty permalinks (needed for result + leaderboard URLs)..."
wp rewrite structure '/%postname%/' --hard >/dev/null 2>&1 || true

echo "==> Activating the NAASE Challenge plugin..."
wp plugin activate naase-challenge

echo "==> Creating host pages (if missing)..."
ensure_page() {
  local title="$1" slug="$2" content="$3"
  if ! wp post list --post_type=page --field=post_name 2>/dev/null | grep -qx "$slug"; then
    wp post create --post_type=page --post_status=publish \
      --post_title="$title" --post_name="$slug" --post_content="$content" >/dev/null
    echo "    created page: /$slug"
  fi
}
ensure_page "SE Basics Challenge" "challenge" "[naase_challenge]"
ensure_page "Leaderboard" "leaderboard" "[naase_leaderboard]"

echo "==> Seeding demo questions (only if the bank is empty)..."
wp eval-file wp-content/plugins/naase-challenge/bin/seed-questions.php || true

echo "==> Flushing rewrite rules..."
wp rewrite flush --hard >/dev/null 2>&1 || true

cat <<EOF

============================================================
  Setup complete.

  Site:        $WP_URL
  Admin:       $WP_URL/wp-admin   ($WP_ADMIN_USER / $WP_ADMIN_PASS)
  Challenge:   $WP_URL/challenge/
  Leaderboard: $WP_URL/leaderboard/

  To demo over the internet:
    ngrok http 8080
============================================================
EOF
