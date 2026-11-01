<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una factura pagada a un proveedor, según el certificado de pago. */
class LineaPago extends Model
{
    protected $table = 'lineas_pago';

    protected $guarded = ['id'];

    protected $casts = [
        'webdox_id' => 'integer', 'n' => 'integer',
        'neto' => 'integer', 'iva' => 'integer', 'exento' => 'integer', 'total' => 'integer',
    ];

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(CertificadoPago::class, 'certificado_id');
    }
}
