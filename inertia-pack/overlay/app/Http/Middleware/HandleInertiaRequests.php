<?php

namespace App\Http\Middleware;

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

    /** Datos compartidos con todas las páginas: los avisos (flash) de la sesión. */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'flash' => fn () => [
                'ok' => $request->session()->get('ok'),
                'error' => $request->session()->get('error'),
                'undo' => $request->session()->get('undo'),
            ],
        ]);
    }
}
