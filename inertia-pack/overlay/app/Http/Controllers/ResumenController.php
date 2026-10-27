<?php

namespace App\Http\Controllers;

use App\Models\Partida;
use App\Support\Presupuesto;
use Inertia\Inertia;
use Inertia\Response;

class ResumenController extends Controller
{
    public function __invoke(): Response
    {
        $cols = Partida::columnasMes();
        $tot = Partida::totalSql();

        // Una sola consulta con la suma de cada mes
        $sumas = Partida::query()
            ->selectRaw(implode(',', array_map(fn ($c) => "COALESCE(SUM($c),0) as $c", $cols)))
            ->first();
        $meses = array_map(fn ($c) => (int) $sumas->{$c}, $cols);

        $agrupar = fn (string $campo) => Partida::query()
            ->selectRaw("$campo as k, SUM($tot) as v")
            ->groupBy($campo)
            ->orderByDesc('v')
            ->get()
            ->map(fn ($r) => [(string) $r->k, (int) $r->v])
            ->all();

        $ejemplos = Partida::where('es_ejemplo', true)->count();
        $sinMonto = Partida::whereRaw("($tot) = 0")->count();
        $sinCodigos = Partida::where(fn ($q) => $q->whereNull('dep_codigo')->orWhere('dep_codigo', '')
            ->orWhereNull('un_codigo')->orWhere('un_codigo', ''))->count();
        $duplicadas = Partida::select('unidad', 'cuenta')->groupBy('unidad', 'cuenta')->havingRaw('COUNT(*) > 1')->get()->count();

        $alertas = [];
        if ($ejemplos) {
            $alertas[] = ['warn', 'Ejemplo', "$ejemplos partidas de ejemplo siguen en el presupuesto. Quítelas en Importar y exportar."];
        }
        if ($sinMonto) {
            $alertas[] = ['bad', 'Revisar', "$sinMonto partidas sin monto en ningún mes."];
        }
        if ($sinCodigos) {
            $alertas[] = ['bad', 'Revisar', "$sinCodigos partidas sin dep_codigo o un_codigo."];
        }
        if ($duplicadas) {
            $alertas[] = ['bad', 'Revisar', "$duplicadas combinaciones unidad + cuenta aparecen más de una vez."];
        }
        if (! $alertas) {
            $alertas[] = ['ok', 'Listo', 'Sin observaciones en los datos.'];
        }

        $total = array_sum($meses);
        $mayor = $total > 0 ? array_search(max($meses), $meses) : null;

        return Inertia::render('Resumen', [
            'meses' => $meses,
            'total' => $total,
            'cantidad' => Partida::count(),
            'ejemplos' => $ejemplos,
            'mayorMes' => $mayor === null ? null : Presupuesto::MESES[$mayor],
            'mayorMonto' => $mayor === null ? 0 : $meses[$mayor],
            'porSigma' => $agrupar('sigma'),
            'porUnidad' => $agrupar('unidad'),
            'alertas' => $alertas,
        ]);
    }
}
