#!/usr/bin/env bash
set -Eeuo pipefail
umask 027

APP_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
cd "$APP_DIR"
command -v flock >/dev/null || { echo 'flock is required for the mail Cron Job.' >&2; exit 1; }
exec 9>"storage/framework/mail-worker.lock"
flock -n 9 || exit 0

# A short-lived worker suits cPanel Cron Jobs; the lock prevents overlapping runs.
exec "$PHP_BIN" artisan queue:work database --queue=mail,default --stop-when-empty --max-time=50 --tries=3 --timeout=35 --sleep=1 --quiet --no-interaction
