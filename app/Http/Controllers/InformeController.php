<?php

namespace App\Http\Controllers;

use App\Support\Anio;
use App\Support\Informe;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InformeController extends Controller
{
    /** Compara el año «b» contra el año base «a»; por defecto, el año en pantalla contra el anterior con datos. */
    public function __invoke(Request $request): Response
    {
        $anios = Anio::disponibles();
        $b = $this->anio($request->query('b')) ?? Anio::actual();
        $anteriores = array_values(array_filter($anios, fn ($x) => $x < $b));
        $a = $this->anio($request->query('a')) ?? ($anteriores[0] ?? $b - 1);

        return Inertia::render('Informe', [
            'a' => $a,
            'b' => $b,
            'filas' => Informe::filas($a, $b),
        ]);
    }

    private function anio(mixed $v): ?int
    {
        $n = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => Anio::MIN, 'max_range' => Anio::MAX]]);

        return $n === false ? null : $n;
    }
}
