<?php

namespace App\Support;

use App\Models\Partida;

/**
 * Informe comparativo entre dos años (p. ej. «Presupuesto 2027 vs Proyección cierre 2026»).
 * Agrupa las partidas por clasificación SIGMA: los ingresos van en positivo y los gastos en negativo.
 */
class Informe
{
    /** Ítems de gasto que siempre aparecen, en este orden (aunque estén en cero). */
    public const GASTOS = [
        'Remuneraciones No Académicas' => ['Remuneraciones No Académicas'],
        'Gastos Operacionales' => ['Gastos Operacionales'],
        'Traspaso de Recursos' => ['Traspaso de Recursos (G)', 'Traspaso de Recursos'],
        'Transferencias a la Universidad' => ['Transferencias a la Universidad'],
    ];

    public const INVERSION = ['Inversión', 'Inversiones'];

    /** Una clasificación es ingreso si es un aporte, un ingreso o un traspaso recibido «(I)». */
    public static function esIngreso(string $sigma): bool
    {
        $s = mb_strtolower($sigma);

        return str_starts_with($s, 'aporte') || str_contains($s, 'ingreso') || str_ends_with($s, '(i)');
    }

    /** @return array<string,int> clasificación SIGMA => total anual */
    public static function totalesPorSigma(int $anio): array
    {
        return Partida::delAnio($anio)
            ->selectRaw('sigma, SUM(' . Partida::totalSql() . ') as v')
            ->groupBy('sigma')
            ->pluck('v', 'sigma')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Filas del informe. Cada una: tipo (total, item, subtotal, neto, separador), nombre,
     * a (año base), b (año comparado), var (b − a) y pct (var / a; null = n/a).
     */
    public static function filas(int $anioA, int $anioB): array
    {
        $ta = self::totalesPorSigma($anioA);
        $tb = self::totalesPorSigma($anioB);
        $sigmas = array_unique([...array_keys($ta), ...array_keys($tb)]);

        $suma = fn (array $t, array $lista) => array_sum(array_map(fn ($s) => $t[$s] ?? 0, $lista));

        $ingresos = array_values(array_filter($sigmas, [self::class, 'esIngreso']));
        $conocidas = [...$ingresos, ...self::INVERSION, ...array_merge(...array_values(self::GASTOS))];

        // Gastos fijos y, después, cualquier otra clasificación de gasto que tenga montos
        $gastos = self::GASTOS;
        $otras = array_diff($sigmas, $conocidas);
        sort($otras);
        foreach ($otras as $s) {
            if (($ta[$s] ?? 0) || ($tb[$s] ?? 0)) {
                $gastos[$s] = [$s];
            }
        }

        $fila = fn (string $tipo, string $nombre, int $a, int $b) => self::fila($tipo, $nombre, $a, $b);

        $ingA = $suma($ta, $ingresos);
        $ingB = $suma($tb, $ingresos);
        $filas = [$fila('total', 'TOTAL INGRESOS', $ingA, $ingB)];

        $gasA = 0;
        $gasB = 0;
        foreach ($gastos as $nombre => $lista) {
            $a = -$suma($ta, $lista);
            $b = -$suma($tb, $lista);
            $gasA += $a;
            $gasB += $b;
            $filas[] = $fila('item', $nombre, $a, $b);
        }
        $filas[] = $fila('total', 'SUBTOTAL GASTOS (SIN INVERSIÓN)', $gasA, $gasB);
        $filas[] = $fila('neto', 'SUBTOTAL FLUJO NETO', $ingA + $gasA, $ingB + $gasB);
        $filas[] = ['tipo' => 'separador'];

        $invA = -$suma($ta, self::INVERSION);
        $invB = -$suma($tb, self::INVERSION);
        $filas[] = $fila('item', 'Inversión', $invA, $invB);
        $filas[] = $fila('neto', 'SUBTOTAL FLUJO NETO POST INVERSIÓN', $ingA + $gasA + $invA, $ingB + $gasB + $invB);

        return $filas;
    }

    /** % = variación / año base; n/a si la base es 0 o si el monto cambia de signo. */
    private static function fila(string $tipo, string $nombre, int $a, int $b): array
    {
        $var = $b - $a;
        $pct = ($a === 0 || $a * $b < 0) ? null : round($var / $a * 100, 2);

        return compact('tipo', 'nombre', 'a', 'b', 'var', 'pct');
    }
}
