<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ES_MANTENIMIENTO no es asignable en masa a proposito: solo la migracion/seeder lo marcan.
#[Fillable(['DESCR', 'ES_CARGO', 'ACTIVO', 'MONTO_DEFAULT'])]
class Concepto extends Model
{
    protected $table = 'CONC1';

    protected $primaryKey = 'NUM_CPTO';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'ES_CARGO' => 'boolean',
            'ACTIVO' => 'boolean',
            'MONTO_DEFAULT' => 'decimal:2',
            'ES_MANTENIMIENTO' => 'boolean',
        ];
    }

    /** El concepto de la cuota de mantenimiento mensual (null si no existe). */
    public static function mantenimiento(): ?self
    {
        return static::where('ES_MANTENIMIENTO', true)->first();
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class, 'NUM_CPTO', 'NUM_CPTO');
    }
}
