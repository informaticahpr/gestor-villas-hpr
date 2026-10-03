<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Propietario o encargado anterior de una villa, con sus datos tal como estaban registrados. */
#[Fillable([
    'CLV_CLIE', 'TIPO', 'PROPIETARIO_ID',
    'NOMBRES', 'APELLIDOS', 'DNI', 'PARENTESCO', 'TELF', 'CELULAR', 'OTRO_TEL', 'MAIL', 'MAIL2', 'FECHA_NAC',
    'DESDE', 'HASTA', 'USUARIO_ID',
])]
class VillaHistorial extends Model
{
    public const PROPIETARIO = 'propietario';

    public const ENCARGADO = 'encargado';

    protected $table = 'villa_historial';

    protected function casts(): array
    {
        return [
            'FECHA_NAC' => 'date',
            'DESDE' => 'date',
            'HASTA' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'USUARIO_ID')->withTrashed();
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->NOMBRES} {$this->APELLIDOS}");
    }
}
