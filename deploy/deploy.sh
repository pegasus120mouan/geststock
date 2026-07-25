#!/usr/bin/env bash
set -euo pipefail

# Script de déploiement Laravel sur serveur Linux/Apache
# Usage (sur le serveur) :
#   cd /var/www/geststock
#   bash deploy/deploy.sh

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"

echo "==> Installation des dépendances Composer"
composer install --no-dev --optimize-autoloader --no-interaction

if [ ! -f .env ]; then
  echo "==> Création du fichier .env"
  cp .env.example .env
  php artisan key:generate --force
  echo "IMPORTANT : éditez .env (DB_*, APP_URL) puis relancez ce script."
  exit 1
fi

echo "==> Migrations"
php artisan migrate --force

echo "==> Lien storage"
php artisan storage:link || true

echo "==> Caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Permissions"
chmod -R ug+rwx storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "==> Déploiement terminé"
echo "Vérifiez que le DocumentRoot Apache pointe vers : $APP_DIR/public"
