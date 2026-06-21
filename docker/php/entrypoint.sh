#!/usr/bin/env sh
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist
fi


if [ "${APP_KEY+set}" = "set" ]; then
  effective_key="${APP_KEY}"                                   
elif [ -f .env ]; then
  effective_key="$(sed -n 's/^APP_KEY=//p' .env | head -n1)"   
else
  effective_key=""
fi

# Valid = "base64:" + base64 that decodes to 16 or 32 bytes (AES-128/256-CBC).
if printf '%s' "${effective_key}" | php -r '$k=trim(stream_get_contents(STDIN)); if(strncmp($k,"base64:",7)===0){$k=base64_decode(substr($k,7),true);} exit(($k!==false && (strlen($k)===32 || strlen($k)===16))?0:1);'; then
  echo "Entrypoint: APP_KEY validated."
else
  if [ "${APP_ENV}" = "local" ]; then
    echo "Entrypoint: APP_KEY missing/invalid for local — generating an ephemeral key..."
    php artisan key:generate --force
  else
    echo "============================================================"
    echo "FATAL: APP_KEY is missing or invalid (APP_ENV=${APP_ENV:-unset})."
    echo "Laravel requires a persistent base64, 32-byte key. Generate one with:"
    echo "  docker run --rm php:8.4-fpm-alpine php -r \"echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;\""
    echo "then set APP_KEY=base64:... in your .env (never auto-generate in production)."
    echo "Refusing to start to avoid serving HTTP 500 MissingAppKeyException."
    echo "============================================================"
    exit 1
  fi
fi

if [ "${WAIT_FOR_DB}" = "true" ]; then
  echo "Waiting for database connection..."
  ATTEMPTS=30
  until php -r "new PDO('mysql:host=' . getenv('DB_HOST') . ';port=3306;dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" >/dev/null 2>&1; do
    ATTEMPTS=$((ATTEMPTS - 1))
    if [ $ATTEMPTS -le 0 ]; then
      echo "Database connection timeout."
      exit 1
    fi
    sleep 1
  done
fi

mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

# Keep local bind-mounted files owned by the host user.
chown -R www-data:www-data storage bootstrap/cache || true

# Only in non-local: chown the source files too (no bind mount in prod)
if [ "${APP_ENV}" != "local" ]; then
  chown -R www-data:www-data . || true
fi

# Directories need execute bits for traversal; regular files do not.
find storage bootstrap/cache -type d -exec chmod 775 {} + || true
find storage bootstrap/cache -type f -exec chmod 664 {} + || true

# Cache config, routes and views for production.
if [ "${APP_ENV}" = "production" ]; then
  echo "Caching Laravel config, routes and views..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

# if [ "${RUN_MIGRATIONS}" = "true" ]; then
#     php artisan migrate --force
# fi

# if [ "${RUN_SEEDERS}" = "true" ]; then
#     php artisan db:seed --force
# fi


### run this instead one time: docker exec -it phpmylager_app_prod php artisan migrate:fresh --seed
exec "$@"
