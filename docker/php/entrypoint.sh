#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist
fi

if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

if [ "${WAIT_FOR_DB}" = "true" ]; then
  echo "Waiting for database connection..."
  ATTEMPTS=30
  until php -r "new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" >/dev/null 2>&1; do
    ATTEMPTS=$((ATTEMPTS - 1))
    if [ $ATTEMPTS -le 0 ]; then
      echo "Database connection timeout."
      exit 1
    fi
    sleep 2
  done
fi

mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

if [ "${RUN_MIGRATIONS}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${RUN_SEEDERS}" = "true" ]; then
    php artisan db:seed --force
fi

exec "$@"