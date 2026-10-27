<?php

namespace App\Support;

use App\Models\Partida;

/**
 * Año y versión de presupuesto que se están viendo. Se guardan en la sesión; si no hay un año elegido
 * (o el elegido ya no tiene datos) se usa el más reciente con partidas, o el año en curso.
 */
class Anio
{
    public const MIN = 2000;

    public const MAX = 2100;

    /** Versiones de un año: el presupuesto inicial y la proyección que se hace desde julio. */
    public const VERSIONES = [
        'inicial' => 'Presupuesto inicial',
        'proyeccion' => 'Proyección',
    ];

    /** @return int[] años con partidas, del más reciente al más antiguo */
    public static function disponibles(): array
    {
        return Partida::query()->distinct()->orderByDesc('anio')->pluck('anio')->map(fn ($a) => (int) $a)->all();
    }

    public static function actual(): int
    {
        $elegido = (int) session('anio');
        $hay = self::disponibles();
        if ($elegido && (in_array($elegido, $hay, true) || ! $hay)) {
            return $elegido;
        }

        return $hay[0] ?? (int) date('Y');
    }

    public static function version(): string
    {
        $v = session('version');

        return isset(self::VERSIONES[$v]) ? $v : 'inicial';
    }

    public static function elegir(int $anio, ?string $version = null): void
    {
        session(['anio' => $anio]);
        if ($version !== null && isset(self::VERSIONES[$version])) {
            session(['version' => $version]);
        }
    }

    /** @return array<int, array<string,int>> año => [versión => cantidad de partidas] */
    public static function cargas(): array
    {
        $out = [];
        Partida::query()->selectRaw('anio, version, COUNT(*) as n')->groupBy('anio', 'version')->orderByDesc('anio')->get()
            ->each(function ($r) use (&$out) {
                $out[(int) $r->anio][$r->version] = (int) $r->n;
            });

        return $out;
    }
}
