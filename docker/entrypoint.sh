#!/usr/bin/env sh
set -eu

COMPOSER_FILE="/var/www/html/composer.json"
VENDOR_AUTOLOAD="/var/www/html/vendor/autoload.php"
HASH_FILE="/var/www/html/vendor/.composer-json.sha1"

current_hash=""
if [ -f "$COMPOSER_FILE" ]; then
  current_hash="$(sha1sum "$COMPOSER_FILE" | awk '{print $1}')"
fi

stored_hash=""
if [ -f "$HASH_FILE" ]; then
  stored_hash="$(cat "$HASH_FILE")"
fi

if [ ! -f "$VENDOR_AUTOLOAD" ] || [ "$current_hash" != "$stored_hash" ]; then
  echo "[entrypoint] Installing composer dependencies..."
  composer install --no-interaction --no-dev --prefer-dist --working-dir=/var/www/html
  mkdir -p /var/www/html/vendor
  printf "%s" "$current_hash" > "$HASH_FILE"
else
  echo "[entrypoint] Composer dependencies are up to date."
fi

exec "$@"
