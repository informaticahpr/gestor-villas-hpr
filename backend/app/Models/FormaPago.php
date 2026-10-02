<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'activo'])]
class FormaPago extends Model
{
    protected $table = 'formas_pago';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function movimientos(): HasMany
    {
        // incluye los anulados: siguen referenciando la forma de pago, asi que tambien impiden borrarla
        return $this->hasMany(Movimiento::class, 'FORMA_PAGO_ID')->withoutGlobalScope('vigentes');
    }
}
