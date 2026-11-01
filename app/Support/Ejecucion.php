<?php

namespace App\Support;

use App\Models\LineaPago;
use App\Models\Partida;

/**
 * Cruza las facturas pagadas (certificados de pago) con el presupuesto por cuenta contable
 * (el código al inicio de la cuenta de la partida). El centro de costo solo indica de dónde salió el pago:
 * sus primeros 8 dígitos son el código de unidad del presupuesto (un_codigo) cuando es una unidad presupuestada.
 */
class Ejecucion
{
    public static function calcular(int $anio, string $version): array
    {
        $tot = Partida::totalSql();
        $filas = [];
        $unidades = [];
        foreach (Partida::delAnio($anio, $version)->selectRaw("un_codigo, unidad, cuenta, SUM($tot) as v")
            ->groupBy('un_codigo', 'unidad', 'cuenta')->get() as $p) {
            $k = self::codigo($p->cuenta);
            $filas[$k] ??= ['clave' => $k, 'cuenta' => $p->cuenta, 'presupuesto' => 0, 'por_unidad' => [], 'pagado' => 0, 'pagado_otros' => 0, 'facturas' => []];
            $filas[$k]['presupuesto'] += (int) $p->v;
            $filas[$k]['por_unidad'][$p->unidad] = ($filas[$k]['por_unidad'][$p->unidad] ?? 0) + (int) $p->v;
            $unidades[$p->un_codigo] = $p->unidad;
        }

        $sinPartida = [];
        $lineas = LineaPago::with('certificado:id,numero,fecha')
            ->whereHas('certificado', fn ($q) => $q->whereYear('fecha', $anio))->get();
        foreach ($lineas as $l) {
            $un = preg_match('/^\d{8}/', (string) $l->centro_costo) ? substr($l->centro_costo, 0, 8) : null;
            $f = [
                'id' => $l->id, 'webdox_id' => $l->webdox_id, 'numero' => $l->certificado?->numero,
                'fecha' => $l->certificado?->fecha?->format('Y-m-d'), 'proveedor' => $l->proveedor, 'factura' => $l->factura,
                'total' => $l->total, 'cuenta' => $l->cuenta_contable, 'centro_costo' => $l->centro_costo, 'obs' => $l->observacion,
                // Unidad presupuestada del centro de costo; null = prorrateo u otra unidad
                'unidad' => $un ? ($unidades[$un] ?? null) : null,
                'origen' => ! $un ? 'prorrateo' : (isset($unidades[$un]) ? 'unidad' : 'otra-unidad'),
            ];
            $k = (string) $l->cuenta_contable;
            if (isset($filas[$k])) {
                $filas[$k][$f['origen'] === 'unidad' ? 'pagado' : 'pagado_otros'] += $l->total;
                $filas[$k]['facturas'][] = $f;

                continue;
            }
            $f['motivo'] = strtoupper($k) === 'VARIOS' ? 'varios' : 'sin-cuenta';
            $sinPartida[] = $f;
        }

        $filas = array_values($filas);
        $pagado = fn ($f) => $f['pagado'] + $f['pagado_otros'];
        usort($filas, fn ($a, $b) => [$pagado($b) != 0, $pagado($b)] <=> [$pagado($a) != 0, $pagado($a)] ?: $b['presupuesto'] <=> $a['presupuesto']);

        return ['filas' => $filas, 'sinPartida' => $sinPartida];
    }

    /** «510207020 Licencias…» → «510207020». */
    public static function codigo(?string $cuenta): string
    {
        return preg_match('/^\s*(\d+)/', (string) $cuenta, $m) ? $m[1] : trim((string) $cuenta);
    }
}
