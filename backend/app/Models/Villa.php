<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Villa (CLV_CLIE = numero de villa). Los datos del dueño viven en Propietario y los del encargado
 * en Encargado. Columnas heredadas: DIR = ubicacion, FCONTRUC = fecha de entrega, NOMED = medidor ENEE.
 */
#[Fillable([
    'CLV_CLIE', 'PROPIETARIO_ID', 'DIR', 'FCONTRUC', 'NOMED', 'CLAVE_CATASTRAL', 'DESCRIPCION_IP',
    'NOHAB', 'NOBATH', 'APLICOBRO', 'CUOTA_ESPECIAL', 'MONTO_CUOTA_ESPECIAL', 'SALDO',
])]
class Villa extends Model
{
    protected $table = 'villas';

    protected $primaryKey = 'CLV_CLIE';

    protected $keyType = 'string';

    public $incrementing = false;

    // casi todas las pantallas y reportes muestran el nombre del propietario junto a la villa
    protected $with = ['propietario'];

    protected function casts(): array
    {
        return [
            'FCONTRUC' => 'date',
            'SALDO' => 'decimal:2',
            'APLICOBRO' => 'boolean',
            'CUOTA_ESPECIAL' => 'boolean',
            'MONTO_CUOTA_ESPECIAL' => 'decimal:2',
        ];
    }

    /**
     * Ordena por numero de villa como lo haria una persona: A-1, A-2, ... A-10 (ordenar en SQL por
     * texto da A-1, A-10, A-11, A-2). Se hace en PHP para no depender del motor (SQLite/PostgreSQL).
     *
     * @param  Collection<int, Villa>  $villas
     * @return Collection<int, Villa>
     */
    public static function enOrdenNatural(Collection $villas): Collection
    {
        return $villas->sortBy('CLV_CLIE', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    /** Todas las villas en orden natural (ver enOrdenNatural). */
    public static function todasEnOrden(): Collection
    {
        return static::enOrdenNatural(static::all());
    }

    /**
     * Crea la villa junto con un propietario nuevo a partir de un arreglo "plano" con los datos de
     * ambos (formato de la tabla anterior). Lo usan los seeders.
     */
    public static function crearConPropietario(array $datos): self
    {
        $campos = (new Propietario)->getFillable();
        $propietario = Propietario::create(array_intersect_key($datos, array_flip($campos)));

        return static::create(array_diff_key($datos, array_flip($campos)) + ['PROPIETARIO_ID' => $propietario->id]);
    }

    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class, 'PROPIETARIO_ID');
    }

    public function encargado(): HasOne
    {
        return $this->hasOne(Encargado::class, 'CLV_CLIE', 'CLV_CLIE');
    }

    /** Propietarios y encargados anteriores (ver VillaHistorial). */
    public function historial(): HasMany
    {
        return $this->hasMany(VillaHistorial::class, 'CLV_CLIE', 'CLV_CLIE');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'CLV_CLIE', 'CLV_CLIE');
    }

    public function getNombreCompletoAttribute(): string
    {
        return $this->propietario?->nombre_completo ?? '';
    }
}
