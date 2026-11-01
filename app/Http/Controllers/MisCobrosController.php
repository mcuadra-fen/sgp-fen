<?php

namespace App\Http\Controllers;

use App\Models\AccesoArea;
use App\Support\CobrosUdt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «Mis cobros»: lo que la UDT le ha cobrado al área de la persona (sus centros de costo asignados). */
class MisCobrosController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        // El administrador puede ver la pantalla tal como la ve un correo (?email=…), para revisar una asignación
        $email = $user->esAdmin() && $request->filled('email') ? strtolower($request->string('email')) : $user->email;
        $centros = AccesoArea::centrosDe($email);
        $catalogo = CobrosUdt::catalogo();

        return Inertia::render('MisCobros', [
            'email' => $email,
            'vistaPrevia' => $email !== strtolower((string) $user->email),
            'centros' => array_map(fn ($c) => ['codigo' => $c, 'nombre' => $catalogo[$c]['nombre'] ?? null], $centros),
            'lineas' => $centros ? CobrosUdt::lineas($centros, $catalogo) : [],
        ]);
    }
}
