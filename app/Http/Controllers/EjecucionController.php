<?php

namespace App\Http\Controllers;

use App\Support\Anio;
use App\Support\Ejecucion;
use Inertia\Inertia;
use Inertia\Response;

/** Presupuesto vs. pagado, por unidad y cuenta contable. */
class EjecucionController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Ejecucion', Ejecucion::calcular(Anio::actual(), Anio::version()));
    }
}
