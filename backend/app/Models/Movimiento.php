<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ANULADO_EN, ANULADO_POR y MOTIVO_ANULACION no son asignables en masa: solo los fija anular().
#[Fillable([
    'CLV_CLIE', 'NUM_CPTO', 'FORMA_PAGO_ID', 'IMPORTE', 'FECHA_APLI', 'FECHA_VENC',
    'ANIO', 'MES', 'REFER', 'OBS', 'USUARIO_ID', 'FOLIO',
])]
class Movimiento extends Model
{
    protected $table = 'movimientos';

    protected $primaryKey = 'ID_MOV';

    protected function casts(): array
    {
        return [
            'IMPORTE' => 'decimal:2',
            'FECHA_APLI' => 'date',
            'FECHA_VENC' => 'date',
            'ANULADO_EN' => 'datetime',
        ];
    }

    /**
     * Los movimientos anulados quedan fuera de TODA consulta por defecto (saldos, estado de cuenta,
     * reportes, dashboard). Solo las pantallas que deben mostrarlos (Reimpresion, el recibo) los
     * piden explicitamente con conAnulados().
     */
    protected static function booted(): void
    {
        static::addGlobalScope('vigentes', fn (Builder $q) => $q->whereNull('movimientos.ANULADO_EN'));
    }

    public static function conAnulados(): Builder
    {
        return static::withoutGlobalScope('vigentes');
    }

    /** El recibo de un movimiento anulado se puede seguir abriendo (sale marcado ANULADO). */
    public function resolveRouteBinding($value, $field = null)
    {
        return static::conAnulados()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    public function anulado(): bool
    {
        return $this->ANULADO_EN !== null;
    }

    public function anular(User $usuario, string $motivo): void
    {
        $this->ANULADO_EN = now();
        $this->ANULADO_POR = $usuario->id;
        $this->MOTIVO_ANULACION = $motivo;
        $this->save();
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class, 'CLV_CLIE', 'CLV_CLIE');
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class, 'NUM_CPTO', 'NUM_CPTO');
    }

    public function formaPago(): BelongsTo
    {
        return $this->belongsTo(FormaPago::class, 'FORMA_PAGO_ID');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'USUARIO_ID');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ANULADO_POR')->withTrashed();
    }
}
