<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mes en que a una villa no se le cobro la cuota de mantenimiento por estar alquilada a producción.
 * No es un movimiento: no lleva monto ni correlativo y no cambia el saldo; solo se muestra en el
 * estado de cuenta.
 */
#[Fillable(['CLV_CLIE', 'FECHA', 'ANIO', 'MES', 'MOTIVO', 'USUARIO_ID'])]
class ExencionCuota extends Model
{
    public const MOTIVO_ALQUILER_PRODUCCION = 'Exenta por alquiler a producción';

    protected $table = 'exenciones_cuota';

    protected function casts(): array
    {
        return [
            'FECHA' => 'date',
        ];
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class, 'CLV_CLIE', 'CLV_CLIE');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'USUARIO_ID');
    }
}
