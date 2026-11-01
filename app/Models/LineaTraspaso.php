<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un centro de costo de un traspaso: «cargo» (cuenta 520214067, el área que paga) o «abono» (620309067, el área que recibe). */
class LineaTraspaso extends Model
{
    protected $table = 'lineas_traspaso';

    protected $guarded = ['id'];

    protected $casts = ['webdox_id' => 'integer', 'monto' => 'integer'];

    public function traspaso(): BelongsTo
    {
        return $this->belongsTo(Traspaso::class);
    }
}
