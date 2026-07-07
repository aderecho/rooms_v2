#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/html}"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.2-fpm}"
WEB_SERVICE="${WEB_SERVICE:-nginx}"

cd "$APP_DIR"

echo "==> Validating SAML PHP files"
php -l app/Http/Controllers/SamlSpController.php
php -l app/Http/Controllers/LoginController.php

echo "==> Installing PHP dependencies from lock file"
composer install --no-dev --optimize-autoloader

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Clearing stale Laravel caches before reading .env"
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear

echo "==> Seeding AMS SAML IdP configuration if seeder exists"
if [ -f database/seeders/AmsSamlConfigurationSeeder.php ]; then
    php artisan db:seed --class=AmsSamlConfigurationSeeder --force
else
    echo "AmsSamlConfigurationSeeder.php not found; skipping SAML seed."
fi

echo "==> Rebuilding optimized Laravel caches"
php artisan optimize

echo "==> Verifying key SAML routes"
php artisan route:list | grep -E "saml2/(login|acs|metadata|logout)|MainDashboard|main_dashboard" || true

echo "==> Reloading services when available"
if command -v systemctl >/dev/null 2>&1; then
    if systemctl list-unit-files "$PHP_FPM_SERVICE.service" >/dev/null 2>&1; then
        sudo systemctl reload "$PHP_FPM_SERVICE"
    else
        echo "$PHP_FPM_SERVICE service not found; skipping PHP-FPM reload."
    fi

    if systemctl list-unit-files "$WEB_SERVICE.service" >/dev/null 2>&1; then
        sudo systemctl reload "$WEB_SERVICE"
    else
        echo "$WEB_SERVICE service not found; skipping web server reload."
    fi
fi

echo "==> SAML deployment complete"
echo "Test: https://fms.upcebu.edu.ph/saml2/login"
