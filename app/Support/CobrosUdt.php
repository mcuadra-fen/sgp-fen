<?php

namespace App\Support;

use App\Models\CentroCosto;
use App\Models\LineaTraspaso;
use App\Models\PagoWebdox;

/**
 * Lo que la UDT cobra a las áreas: líneas de cargo de los comprobantes de solicitudes «Traspaso (Cobros DEA)» de la UDT
 * (la carpeta compartida también tiene comprobantes de otros formularios; esos no cuentan).
 */
class CobrosUdt
{
    /** @return array<string, array{area:?string, nombre:?string}> centro de costo => nombre */
    public static function catalogo(): array
    {
        $catalogo = CentroCosto::catalogo();
        // Centros de costo que no están en el tarifario: si la solicitud de Webdox cobra a una sola área, su «Departamento» la nombra
        $departamentos = PagoWebdox::where('tipo', 'traspaso')->whereNotNull('departamento')->pluck('departamento', 'webdox_id');
        foreach (LineaTraspaso::where('lado', 'cargo')->get()->groupBy('traspaso_id') as $ls) {
            $l = $ls->first();
            if ($ls->count() === 1 && ! isset($catalogo[$l->centro_costo]) && isset($departamentos[$l->webdox_id])) {
                $catalogo[$l->centro_costo] = ['area' => null, 'nombre' => $departamentos[$l->webdox_id]];
            }
        }
        foreach (Traspasos::UDT as $u) {
            $catalogo += [$u . '4201001' => ['area' => 'UDT', 'nombre' => 'Unidad de Desarrollo Tecnológico']];
        }

        return $catalogo;
    }

    /**
     * @param  string[]|null  $centros  solo estos centros de costo (null = todos)
     * @return array<int, array<string, mixed>> una línea por área cobrada, de la más reciente a la más antigua
     */
    public static function lineas(?array $centros = null, ?array $catalogo = null): array
    {
        $catalogo ??= self::catalogo();

        return LineaTraspaso::with('traspaso:id,comprobante,fecha,glosa,sentido')->where('lado', 'cargo')
            ->whereIn('webdox_id', PagoWebdox::where('tipo', 'traspaso')->select('webdox_id'))
            ->whereHas('traspaso', fn ($q) => $q->where('sentido', 'cobro'))
            ->when($centros !== null, fn ($q) => $q->whereIn('centro_costo', $centros))->get()
            ->sortByDesc(fn ($l) => [$l->traspaso->fecha?->format('Y-m-d'), $l->monto])
            ->map(fn (LineaTraspaso $l) => [
                'id' => $l->id, 'webdox_id' => $l->webdox_id, 'comprobante' => $l->traspaso->comprobante,
                'fecha' => $l->traspaso->fecha?->format('Y-m-d'), 'glosa' => $l->traspaso->glosa,
                'centro_costo' => $l->centro_costo, 'monto' => $l->monto,
                'area' => $catalogo[$l->centro_costo]['area'] ?? null, 'nombre' => $catalogo[$l->centro_costo]['nombre'] ?? null,
            ])->values()->all();
    }
}
