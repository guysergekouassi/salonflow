#!/bin/sh
# Démarrage du conteneur de démonstration
set -e
cd /app

[ -f .env ] || cp .env.example .env
# Clé de chiffrement : fournie par l'hébergeur (APP_KEY) ou générée au démarrage
[ -n "$APP_KEY" ] || php artisan key:generate --force --no-interaction

touch database/database.sqlite
php artisan salon:demo --fresh --no-interaction
php artisan optimize

# Remise à zéro de la démo chaque nuit (planificateur Laravel)
php artisan schedule:work > /dev/stdout 2>&1 &

exec php artisan serve --no-reload --host=0.0.0.0 --port="${PORT:-8080}"
