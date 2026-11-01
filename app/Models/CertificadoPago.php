<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un Excel «ID####_Certificado de Pago.xlsx»; webdox_id es el ID de la solicitud en Webdox. */
class CertificadoPago extends Model
{
    protected $table = 'certificados_pago';

    protected $guarded = ['id'];

    protected $casts = ['webdox_id' => 'integer', 'fecha' => 'date', 'modificado' => 'integer'];

    public function lineas(): HasMany
    {
        return $this->hasMany(LineaPago::class, 'certificado_id')->orderBy('n');
    }
}
