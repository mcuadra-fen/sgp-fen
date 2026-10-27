<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nota sobre una partida; una partida puede tener varias. */
class Observacion extends Model
{
    protected $table = 'observaciones';

    protected $fillable = ['texto'];

    public function partida(): BelongsTo
    {
        return $this->belongsTo(Partida::class);
    }

    public function paraInterfaz(): array
    {
        return [
            'id' => $this->id,
            'texto' => $this->texto,
            'fecha' => $this->created_at?->format('d-m-Y H:i'),
        ];
    }
}
