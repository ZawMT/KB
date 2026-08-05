#!/usr/bin/env bash
#
# Deploy script for a Laravel app on a manually-provisioned VPS.
# Install as /var/www/myapp/deploy.sh, then: chmod +x deploy.sh && ./deploy.sh
#
# This is the simple version: it deploys in place, with a short maintenance
# window. For zero downtime, use a releases/current symlink layout (Deployer).

set -euo pipefail   # exit on error, on unset variable, and on any failed pipe stage

APP_DIR="/var/www/myapp"
PHP_FPM="php8.4-fpm"
BRANCH="main"

cd "$APP_DIR"

echo "==> Maintenance mode"
# --retry tells clients (and search engines) to come back shortly.
# "|| true" because the app may already be down from a failed previous run.
php artisan down --retry=15 || true

# Whatever happens below — success, error, or Ctrl-C — bring the app back up.
# Without this, a failed deploy leaves the site stuck on the maintenance page.
trap 'php artisan up || true' EXIT

echo "==> Pulling $BRANCH"
git pull origin "$BRANCH"

echo "==> Composer"
# --no-dev omits dev-only packages; --optimize-autoloader builds a classmap.
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Frontend assets"
# Skipped automatically if the project has no package.json.
if [ -f package.json ]; then
    npm ci
    npm run build
fi

echo "==> Migrations"
# --force is required: there is no terminal here to confirm the prompt.
php artisan migrate --force

echo "==> Rebuilding caches"
# Clear first, then rebuild — a stale cached config can otherwise be baked
# back in. Note: once config is cached, env() returns null outside config/*.php.
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Reloading PHP-FPM"
# PHP caches compiled bytecode in OPcache; without this reload the old code
# keeps being served. Needs a sudoers entry to run unattended:
#   deploy ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.4-fpm
sudo systemctl reload "$PHP_FPM"

echo "==> Restarting queue workers"
# Workers hold code in memory. This signals them to exit after their current
# job; Supervisor then restarts them running the new code.
php artisan queue:restart

echo "==> Done"
# The EXIT trap runs 'artisan up' here.
