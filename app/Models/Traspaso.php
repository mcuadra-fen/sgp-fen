<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un comprobante contable de traspaso de recursos; webdox_id es el ID de la solicitud en Webdox. */
class Traspaso extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['webdox_id' => 'integer', 'fecha' => 'date', 'total' => 'integer', 'modificado' => 'integer'];

    public const SENTIDOS = ['cobro' => 'Cobro UDT', 'pago' => 'Pagado por UDT', 'otro' => 'Entre otras áreas'];

    public function lineas(): HasMany
    {
        return $this->hasMany(LineaTraspaso::class);
    }
}
