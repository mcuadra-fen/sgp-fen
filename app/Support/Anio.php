<?php

namespace App\Support;

use App\Models\Partida;

/**
 * Año de presupuesto que se está viendo. Se guarda en la sesión; si no hay uno elegido
 * (o el elegido ya no tiene datos) se usa el más reciente con partidas, o el año en curso.
 */
class Anio
{
    public const MIN = 2000;

    public const MAX = 2100;

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

    public static function elegir(int $anio): void
    {
        session(['anio' => $anio]);
    }
}
