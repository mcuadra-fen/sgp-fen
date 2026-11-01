<?php

namespace App\Http\Middleware;

use App\Support\Anio;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /** Vista Blade raíz (resources/views/app.blade.php). */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** Datos compartidos con todas las páginas: el año que se ve y los avisos (flash) de la sesión. */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            // Persona que ingresó por Keycloak (null en la pantalla de ingreso)
            'usuario' => fn () => $request->user() ? [
                'nombre' => $request->user()->name,
                'email' => $request->user()->email,
                'admin' => $request->user()->esAdmin(),
            ] : null,
            'anio' => fn () => Anio::actual(),
            'anios' => fn () => Anio::disponibles(),
            'version' => fn () => Anio::version(),
            'versiones' => Anio::VERSIONES,
            // año => [versión => n], para marcar en el selector las versiones sin datos
            'cargas' => fn () => (object) Anio::cargas(),
            'flash' => fn () => [
                'ok' => $request->session()->get('ok'),
                'error' => $request->session()->get('error'),
                'undo' => $request->session()->get('undo'),
            ],
        ]);
    }
}
