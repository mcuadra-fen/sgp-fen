#!/usr/bin/env bash
# Crea el proyecto Laravel, instala Inertia, copia la aplicación del presupuesto encima y la deja corriendo.
# Requisitos: PHP 8.2+, Composer, Node 18+ y npm.
# Uso: bash setup.sh [carpeta-destino]
set -euo pipefail

DEST="${1:-presupuesto-udt-inertia}"
AQUI="$(cd "$(dirname "$0")" && pwd)"

composer create-project laravel/laravel "$DEST" --prefer-dist
cd "$DEST"
composer require inertiajs/inertia-laravel
cd ..

# Sobrescribe, entre otros, bootstrap/app.php (registra el middleware de Inertia),
# package.json, vite.config.js, tailwind.config.js y routes/web.php del esqueleto.
cp -R "$AQUI/overlay/." "$DEST/"
cd "$DEST"

npm install
php artisan migrate --seed --force   # el esqueleto usa SQLite: crea database/database.sqlite si falta
npm run build

echo
echo "Listo. Para verlo:  cd $DEST && php artisan serve   (http://127.0.0.1:8000)"
echo "Para probar:        php artisan test"
