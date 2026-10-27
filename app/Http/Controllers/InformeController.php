<?php

namespace App\Http\Controllers;

use App\Support\Anio;
use App\Support\Informe;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InformeController extends Controller
{
    /**
     * Compara el año «b» (versión «vb») contra el año base «a» (versión «va»). Por defecto, lo que está
     * en pantalla contra el año anterior con datos, usando su proyección si la tiene.
     */
    public function __invoke(Request $request): Response
    {
        $anios = Anio::disponibles();
        $cargas = Anio::cargas();
        $b = $this->anio($request->query('b')) ?? Anio::actual();
        $vb = $this->version($request->query('vb')) ?? Anio::version();
        $anteriores = array_values(array_filter($anios, fn ($x) => $x < $b));
        $a = $this->anio($request->query('a')) ?? ($anteriores[0] ?? $b - 1);
        $va = $this->version($request->query('va')) ?? (isset($cargas[$a]['proyeccion']) ? 'proyeccion' : 'inicial');

        return Inertia::render('Informe', [
            'a' => $a,
            'va' => $va,
            'b' => $b,
            'vb' => $vb,
            'filas' => Informe::filas($a, $b, $va, $vb),
        ]);
    }

    private function anio(mixed $v): ?int
    {
        $n = filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => Anio::MIN, 'max_range' => Anio::MAX]]);

        return $n === false ? null : $n;
    }

    private function version(mixed $v): ?string
    {
        return is_string($v) && isset(Anio::VERSIONES[$v]) ? $v : null;
    }
}
