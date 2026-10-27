<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Clasificación propia de las partidas (p. ej. «Licencias transversales», «Proyectos»). */
class Categoria extends Model
{
    protected $table = 'categorias';

    protected $fillable = ['nombre'];

    protected static function booted(): void
    {
        // Las partidas de una categoría eliminada quedan sin categoría (incluidas las de la papelera)
        static::deleting(fn (Categoria $c) => Partida::withTrashed()->where('categoria_id', $c->id)->update(['categoria_id' => null]));
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(Partida::class);
    }
}
