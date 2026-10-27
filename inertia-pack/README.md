# Presupuesto UDT: Laravel + Inertia + React + Tailwind

Backend Laravel (modelo, validación, base de datos) con interfaz React servida por Inertia: sin API separada ni rutas duplicadas. Tailwind CSS 3 y Vite.

## Puesta en marcha

Requisitos: PHP 8.2+, Composer, Node 18+.

**Linux / macOS / Git Bash**
```bash
bash setup.sh                       # crea ./presupuesto-udt-inertia
cd presupuesto-udt-inertia
php artisan serve                   # http://127.0.0.1:8000
php artisan test
```

**Windows (CMD o PowerShell, con XAMPP)**
```
composer create-project laravel/laravel presupuesto-udt-inertia
cd presupuesto-udt-inertia
composer require inertiajs/inertia-laravel
cd ..
xcopy /E /Y inertia-pack\overlay\* presupuesto-udt-inertia\
cd presupuesto-udt-inertia
npm install
php artisan migrate --seed
npm run build
php artisan serve
```
`xcopy` sobrescribe `bootstrap/app.php`, `package.json`, `vite.config.js`, `tailwind.config.js` y `routes/web.php` del esqueleto; es lo esperado.

Desarrollo con recarga: `npm run dev` en una terminal y `php artisan serve` en otra.

## Cómo se reparten las tareas
- **Laravel**: datos, validación (`PartidaRequest`), borrado lógico, importación/exportación y cada página como `Inertia::render('Pagina', [...props])`.
- **React** (`resources/js/Pages`): Resumen, Presupuesto, Formulario de partida e Importar/Exportar. La búsqueda y los filtros de la grilla corren en el navegador; cada monto editado se guarda al instante con `PATCH /partidas/{id}/mes`.
- `resources/js/components/Grilla.jsx` es la tabla; no depende de Inertia, así que se puede reemplazar por AG Grid o Handsontable sin tocar el resto.

## Estructura
- `app/Support/Presupuesto.php` formato es-CL, reparto mensual y lectura/escritura TSV (PHP puro).
- `app/Models/Partida.php` tabla `partidas`: 6 campos descriptivos, `m1`..`m12`, `dep_codigo`, `un_codigo`.
- `app/Http/Controllers/` `ResumenController`, `PartidaController`, `DatosController`.
- `app/Http/Middleware/HandleInertiaRequests.php` comparte los avisos (flash) con React.
- `database/` migración y `PartidaSeeder` (2 filas reales y 3 de ejemplo marcadas).
- `tests/Feature/PresupuestoTest.php` pruebas de rutas, props de Inertia, validación e importación.

## Pendiente para producción
Autenticación y roles (hoy cualquier persona con acceso a la URL edita), bitácora de cambios por usuario, MySQL/PostgreSQL en lugar de SQLite y el modelo de las 27 pestañas del Excel.
