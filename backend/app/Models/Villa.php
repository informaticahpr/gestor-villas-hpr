<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'CLV_CLIE', 'NOMBRES', 'APELLIDOS', 'DIR', 'TELF', 'CELULAR', 'OTRO_TEL',
    'MAIL', 'MAIL2', 'FCONTRUC', 'NOMED', 'FECHA_NAC', 'NOHAB', 'NOBATH',
    'APLICOBRO', 'CUOTA_ESPECIAL', 'MONTO_CUOTA_ESPECIAL', 'SALDO',
])]
class Villa extends Model
{
    protected $table = 'CLIE1';

    protected $primaryKey = 'CLV_CLIE';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'FCONTRUC' => 'date',
            'FECHA_NAC' => 'date',
            'SALDO' => 'decimal:2',
            'APLICOBRO' => 'boolean',
            'CUOTA_ESPECIAL' => 'boolean',
            'MONTO_CUOTA_ESPECIAL' => 'decimal:2',
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'CLV_CLIE', 'CLV_CLIE');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->NOMBRES} {$this->APELLIDOS}");
    }
}
