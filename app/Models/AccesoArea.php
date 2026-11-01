<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un centro de costo que la persona del correo puede ver en «Mis cobros». */
class AccesoArea extends Model
{
    protected $table = 'accesos_area';

    protected $guarded = ['id'];

    /** @return string[] centros de costo asignados al correo */
    public static function centrosDe(?string $email): array
    {
        return $email ? static::where('email', strtolower(trim($email)))->pluck('centro_costo')->all() : [];
    }
}
