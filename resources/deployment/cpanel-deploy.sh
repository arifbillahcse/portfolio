#!/usr/bin/env bash
set -Eeuo pipefail
umask 022

# Upload the backend outside public_html and point the domain at its public/ directory.
APP_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"
FIRST_INSTALL=0
BUNDLED_VENDOR=0
for option in "$@"; do
case "$option" in
  --first-install) FIRST_INSTALL=1 ;;
  --bundled-vendor) BUNDLED_VENDOR=1 ;;
  --help)
    printf '%s\n' \
      'Usage: bash resources/deployment/cpanel-deploy.sh [--first-install] [--bundled-vendor]' \
      'Set PHP_BIN to your cPanel PHP 8.3+ CLI and COMPOSER_BIN to the Composer PHP/phar path.' \
      'Before first use: create the MySQL database/user, configure .env from .env.mysql.example,' \
      'install dependencies, and run php artisan key:generate ONCE for a new installation.' \
      'Keep the production .env, APP_KEY, database, and storage/app/public across updates.' \
      'Back up the database and uploads before updating. Set the domain document root to public/.' \
      'If public_html is fixed, use public_html-index.php and set APP_PUBLIC_PATH in .env.' \
      '--first-install seeds starter content and prompts for a new administrator.' \
      '--bundled-vendor skips Composer when using the complete release ZIP including vendor/.' \
      'Configure HTTPS, the Cron Job from cpanel.cron.example, and your SMTP provider.' \
      'Enable SITE_INDEXABLE only after checking the live website.'
    exit 0 ;;
  *) echo 'Unknown option. Use --help.' >&2; exit 2 ;;
esac
done
cd "$APP_DIR"
[[ -f artisan && -f composer.lock && -f .env && -f public/index.php ]] || {
  echo 'Laravel files or .env are missing. Configure this backend before deploying.' >&2; exit 1;
}
if [[ "$BUNDLED_VENDOR" == 0 && ( -z "$COMPOSER_BIN" || ! -f "$COMPOSER_BIN" ) ]]; then
  echo 'Set COMPOSER_BIN to the absolute Composer PHP/phar path.' >&2; exit 1;
fi
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' || {
  echo 'The selected PHP CLI must be version 8.3 or later.' >&2; exit 1;
}
command -v flock >/dev/null || { echo 'flock is required to prevent overlapping deployments.' >&2; exit 1; }
exec 9>"storage/framework/deployment.lock"
flock -n 9 || { echo 'Another deployment is running.' >&2; exit 1; }

# Environment files, encryption keys, uploads, and existing records are not replaced.
if [[ "$BUNDLED_VENDOR" == 1 ]]; then
  [[ -f vendor/autoload.php && -f vendor/composer/installed.php ]] || {
    echo 'The bundled production dependencies are missing. Upload the complete release ZIP.' >&2; exit 1;
  }
  "$PHP_BIN" -r 'require "vendor/autoload.php";'
else
  "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --prefer-dist --no-interaction --optimize-autoloader
fi
"$PHP_BIN" artisan config:clear --no-interaction
"$PHP_BIN" artisan filament:assets --no-interaction
if [[ ! -e public/storage && ! -L public/storage ]]; then
  "$PHP_BIN" artisan storage:link --no-interaction
fi
"$PHP_BIN" artisan portfolio:check-production --before-migrate --no-interaction

MAINTENANCE=0
on_error() {
  if [[ "$MAINTENANCE" == 1 ]]; then
    echo 'Deployment failed; maintenance mode remains enabled. Resolve the error and rerun deployment.' >&2
  else
    echo 'Deployment stopped before maintenance mode. Resolve the reported error and retry.' >&2
  fi
}
trap on_error ERR
"$PHP_BIN" artisan down --retry=60 --no-interaction
MAINTENANCE=1
"$PHP_BIN" artisan migrate --force --no-interaction
if [[ "$FIRST_INSTALL" == 1 ]]; then
  "$PHP_BIN" artisan db:seed --class=PortfolioContentSeeder --force --no-interaction
  "$PHP_BIN" artisan portfolio:admin
fi
"$PHP_BIN" artisan optimize --no-interaction
"$PHP_BIN" artisan filament:optimize --no-interaction
"$PHP_BIN" artisan portfolio:check-production --no-interaction
"$PHP_BIN" artisan up --no-interaction
MAINTENANCE=0
"$PHP_BIN" artisan queue:restart --no-interaction
echo 'Deployment commands completed. Check HTTPS /up, public pages, admin login, uploads, a real inquiry, and email delivery.'
