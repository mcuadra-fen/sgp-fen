<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Nombre de un centro de costo (15 dígitos), tomado del tarifario de traspasos fijos de la UDT. */
class CentroCosto extends Model
{
    protected $table = 'centros_costo';

    protected $guarded = ['id'];

    /** @return array<string, array{area:?string, nombre:?string}> */
    public static function catalogo(): array
    {
        return static::get(['codigo', 'area', 'nombre'])->keyBy('codigo')
            ->map(fn ($c) => ['area' => $c->area, 'nombre' => $c->nombre])->all();
    }
}
