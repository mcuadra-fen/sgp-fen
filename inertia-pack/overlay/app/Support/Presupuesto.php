<?php

namespace App\Support;

/**
 * Lógica pura del presupuesto (sin dependencias de Laravel): formato es-CL,
 * reparto mensual, lectura de filas pegadas desde Excel y exportación TSV.
 */
final class Presupuesto
{
    public const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public const ABREV = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    public const CABECERA = [
        'DPTO', 'UNIDAD', 'ACTIVIDAD', 'SUBACTIVIDAD', 'CLASIFICACIÓN SIGMA', 'CUENTA',
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        'MONTO TOTAL', 'dep_codigo', 'un_codigo',
    ];

    /** "1.234.567" -> 1234567. Solo conserva dígitos; no admite negativos. */
    public static function parseMonto(mixed $v): int
    {
        $d = preg_replace('/\D/', '', (string) $v);

        return $d === '' ? 0 : (int) $d;
    }

    public static function fmt(int|float $v): string
    {
        return number_format((int) round($v), 0, ',', '.');
    }

    /** 12.500.000 -> "12,5 M"; bajo un millón usa el formato completo. */
    public static function fmtM(int|float $v): string
    {
        if ($v < 1_000_000) {
            return self::fmt($v);
        }
        $s = number_format($v / 1_000_000, 1, ',', '.');

        return rtrim(rtrim($s, '0'), ',') . ' M';
    }

    /**
     * Reparte $total en enteros. El resto se suma de a 1 a los primeros meses
     * del reparto, de modo que la suma sea siempre exacta.
     *
     * @return int[] 12 montos
     */
    public static function reparto(int $total, string $modo = 'uniforme', int $mes = 0): array
    {
        $m = array_fill(0, 12, 0);
        $spread = function (array $idx) use (&$m, $total): void {
            $n = count($idx);
            $base = intdiv($total, $n);
            $resto = $total - $base * $n;
            foreach ($idx as $k => $i) {
                $m[$i] = $base + ($k < $resto ? 1 : 0);
            }
        };
        match ($modo) {
            'trimestral' => $spread([0, 3, 6, 9]),
            'semestral' => $spread([0, 6]),
            'unico' => $m[max(0, min(11, $mes))] = $total,
            default => $spread(range(0, 11)),
        };

        return $m;
    }

    public static function niceMax(float $v): float
    {
        if ($v <= 0) {
            return 1.0;
        }
        $p = 10 ** floor(log10($v));
        $n = $v / $p;
        $f = $n <= 1 ? 1 : ($n <= 2 ? 2 : ($n <= 2.5 ? 2.5 : ($n <= 5 ? 5 : 10)));

        return (float) ($f * $p);
    }

    /**
     * Lee texto pegado desde Excel (TSV). Columnas: DPTO, UNIDAD, ACTIVIDAD, SUBACTIVIDAD,
     * CLASIFICACIÓN SIGMA, CUENTA, 12 meses, MONTO TOTAL, dep_codigo, un_codigo.
     *
     * @return array{filas: array<int,array<string,mixed>>, errores: string[], avisos: int}
     */
    public static function parsePegado(string $txt): array
    {
        $filas = [];
        $errores = [];
        $avisos = 0;
        foreach (preg_split('/\r?\n/', $txt) as $n => $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $c = explode("\t", $linea);
            if (strtoupper(trim($c[0])) === 'DPTO') {
                continue; // encabezado
            }
            if (count($c) < 18) {
                $errores[] = 'Línea ' . ($n + 1) . ': tiene ' . count($c) . ' columnas y se esperan al menos 18.';
                continue;
            }
            if (trim($c[1]) === '' || trim($c[5]) === '') {
                $errores[] = 'Línea ' . ($n + 1) . ': falta UNIDAD o CUENTA.';
                continue;
            }
            $meses = array_map([self::class, 'parseMonto'], array_slice($c, 6, 12));
            if (isset($c[18]) && abs(self::parseMonto($c[18]) - array_sum($meses)) > 12) {
                $avisos++;
            }
            $filas[] = [
                'dpto' => trim($c[0]),
                'unidad' => trim($c[1]),
                'actividad' => trim($c[2]),
                'subactividad' => trim($c[3]),
                'sigma' => trim($c[4]),
                'cuenta' => trim($c[5]),
                'meses' => $meses,
                'dep_codigo' => trim($c[19] ?? ''),
                'un_codigo' => trim($c[20] ?? ''),
            ];
        }

        return ['filas' => $filas, 'errores' => $errores, 'avisos' => $avisos];
    }

    /** @param array<int,array<string,mixed>> $filas cada fila con las claves de parsePegado() */
    public static function toTsv(array $filas): string
    {
        $out = [implode("\t", self::CABECERA)];
        foreach ($filas as $f) {
            $out[] = implode("\t", [
                $f['dpto'], $f['unidad'], $f['actividad'], $f['subactividad'], $f['sigma'], $f['cuenta'],
                ...$f['meses'], array_sum($f['meses']), $f['dep_codigo'], $f['un_codigo'],
            ]);
        }

        return implode("\n", $out);
    }
}
