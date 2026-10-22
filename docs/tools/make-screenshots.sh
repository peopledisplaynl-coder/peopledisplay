#!/usr/bin/env bash
# docs/tools/make-screenshots.sh
# One command that rebuilds docs/images/*.png: copies the app into a temporary folder,
# fills a throw-away MariaDB/MySQL database with fictional demo data, starts two PHP
# development servers (demo + fresh installer) and runs docs/tools/screenshots.mjs.
#
# Needs: Linux/macOS shell, PHP 8 CLI with pdo_mysql, a disposable MariaDB/MySQL server,
# Node 20+, `npm install` done (playwright-core) and a Chromium that matches it
# (npx playwright-core install chromium, or PLAYWRIGHT_BROWSERS_PATH).
#
#   DB_USER=pd DB_PASS=pd [DB_HOST=127.0.0.1] [DB_PORT=3306] [CDN_DIR=…] docs/tools/make-screenshots.sh [shot-name …]
#
# The databases pd_demo and pd_install are DROPPED and recreated — never use a real server.

set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="${WORK:-$(mktemp -d)}"; mkdir -p "$WORK"
DB_HOST="${DB_HOST:-127.0.0.1}"; DB_PORT="${DB_PORT:-3306}"
: "${DB_USER:?set DB_USER}"; : "${DB_PASS:?set DB_PASS}"
DEMO_PORT="${DEMO_PORT:-8080}"; INSTALL_PORT="${INSTALL_PORT:-8081}"

echo "→ copying the app to $WORK"
cd "$ROOT"
node -e "import('./scripts/lib/files.mjs').then(m => console.log(m.filesForProfile('golden').join('\n')))" > "$WORK/files.txt"
for d in app install; do
  rm -rf "$WORK/$d"; mkdir -p "$WORK/$d"
  rsync -a --files-from="$WORK/files.txt" "$ROOT/" "$WORK/$d/"
  mkdir -p "$WORK/$d/uploads/profiles" "$WORK/$d/logs" "$WORK/$d/tmp/sessions" "$WORK/$d/tmp/badge_photos" "$WORK/$d/backups"
done

echo "→ demo database"
PD_DEMO_DOMAIN=demo.peopledisplay.test php docs/tools/demo-seed.php "$WORK/app" "$DB_HOST" "$DB_PORT" "$DB_USER" "$DB_PASS" pd_demo
php -r '$p=new PDO("mysql:host='"$DB_HOST"';port='"$DB_PORT"'", "'"$DB_USER"'", "'"$DB_PASS"'"); $p->exec("DROP DATABASE IF EXISTS pd_install");'

echo "→ starting PHP servers"
php -S "127.0.0.1:$DEMO_PORT" -t "$WORK/app" > "$WORK/demo.log" 2>&1 & DEMO_PID=$!
php -S "127.0.0.1:$INSTALL_PORT" -t "$WORK/install" > "$WORK/install.log" 2>&1 & INSTALL_PID=$!
trap 'kill $DEMO_PID $INSTALL_PID 2>/dev/null || true' EXIT
sleep 1

echo "→ screenshots"
PD_BASE="http://demo.peopledisplay.test" \
PD_INSTALL_BASE="http://install.peopledisplay.test" \
PD_HOST_RULES="MAP demo.peopledisplay.test 127.0.0.1:$DEMO_PORT, MAP install.peopledisplay.test 127.0.0.1:$INSTALL_PORT" \
PD_INSTALL_DB_HOST="$DB_HOST" PD_INSTALL_DB_USER="$DB_USER" PD_INSTALL_DB_PASS="$DB_PASS" \
PD_CDN_DIR="${CDN_DIR:-}" \
node docs/tools/screenshots.mjs "$@"

# Smaller PNGs for the repository and the manuals (optional tool)
if command -v pngquant >/dev/null 2>&1; then
  pngquant --quality=75-95 --skip-if-larger --force --ext .png docs/images/*.png || true
  echo "→ screenshots compressed with pngquant"
fi
