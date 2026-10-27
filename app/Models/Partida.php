<?php

namespace App\Models;

use App\Support\Anio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una línea del presupuesto de un año: DPTO / UNIDAD / ACTIVIDAD / SUBACTIVIDAD / SIGMA / CUENTA
 * con su monto en cada mes (m1 = enero ... m12 = diciembre).
 *
 * @property-read int $total suma de los 12 meses
 */
class Partida extends Model
{
    use SoftDeletes;

    protected $table = 'partidas';

    protected $fillable = [
        'anio', 'dpto', 'unidad', 'actividad', 'subactividad', 'sigma', 'cuenta',
        'dep_codigo', 'un_codigo', 'es_ejemplo',
        'm1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7', 'm8', 'm9', 'm10', 'm11', 'm12',
    ];

    protected $casts = [
        'anio' => 'integer',
        'categoria_id' => 'integer',
        'es_ejemplo' => 'boolean',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function observaciones(): HasMany
    {
        return $this->hasMany(Observacion::class)->orderBy('id');
    }

    /** Solo las partidas del año que se está viendo (o del indicado). */
    public function scopeDelAnio(Builder $q, ?int $anio = null): void
    {
        $q->where('anio', $anio ?? Anio::actual());
    }

    /** @return string[] m1..m12 */
    public static function columnasMes(): array
    {
        return array_map(fn (int $i) => 'm' . $i, range(1, 12));
    }

    /** Expresión SQL con el total anual de una fila: m1+m2+...+m12 */
    public static function totalSql(): string
    {
        return implode('+', self::columnasMes());
    }

    /** @return int[] los 12 montos en orden */
    public function meses(): array
    {
        return array_map(fn (string $c) => (int) $this->{$c}, self::columnasMes());
    }

    /** @param int[] $valores */
    public function setMeses(array $valores): static
    {
        foreach (self::columnasMes() as $i => $c) {
            $this->{$c} = (int) ($valores[$i] ?? 0);
        }

        return $this;
    }

    protected function total(): Attribute
    {
        return Attribute::get(fn () => array_sum($this->meses()));
    }

    /** Fila en el formato que usa App\Support\Presupuesto (importar/exportar). */
    public function comoFila(): array
    {
        return [
            'dpto' => (string) $this->dpto,
            'unidad' => (string) $this->unidad,
            'actividad' => (string) $this->actividad,
            'subactividad' => (string) $this->subactividad,
            'sigma' => (string) $this->sigma,
            'cuenta' => (string) $this->cuenta,
            'meses' => $this->meses(),
            'dep_codigo' => (string) $this->dep_codigo,
            'un_codigo' => (string) $this->un_codigo,
        ];
    }

    /** Datos que recibe la interfaz React. */
    public function paraInterfaz(): array
    {
        return [
            'id' => $this->id,
            'dpto' => (string) $this->dpto,
            'unidad' => (string) $this->unidad,
            'actividad' => (string) $this->actividad,
            'subactividad' => (string) $this->subactividad,
            'sigma' => (string) $this->sigma,
            'cuenta' => (string) $this->cuenta,
            'dep_codigo' => (string) $this->dep_codigo,
            'un_codigo' => (string) $this->un_codigo,
            'es_ejemplo' => (bool) $this->es_ejemplo,
            'categoria' => $this->categoria?->nombre,
            'n_obs' => (int) ($this->observaciones_count ?? 0),
            'meses' => $this->meses(),
        ];
    }
}
