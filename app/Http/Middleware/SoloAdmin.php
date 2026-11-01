<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El sistema completo es solo para administradores; las personas de un área van a «Mis cobros». */
class SoloAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->esAdmin()) {
            return $request->isMethod('GET') ? redirect()->route('mis-cobros') : abort(403);
        }

        return $next($request);
    }
}
