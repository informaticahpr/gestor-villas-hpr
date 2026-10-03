<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Persona que atiende la villa en nombre del propietario (opcional, uno por villa). */
#[Fillable(['CLV_CLIE', 'NOMBRES', 'APELLIDOS', 'DNI', 'TELF', 'CELULAR', 'OTRO_TEL', 'MAIL', 'MAIL2', 'FECHA_NAC'])]
class Encargado extends Model
{
    protected $table = 'encargados';

    public const CAMPOS = ['NOMBRES', 'APELLIDOS', 'DNI', 'TELF', 'CELULAR', 'OTRO_TEL', 'MAIL', 'MAIL2', 'FECHA_NAC'];

    protected function casts(): array
    {
        return [
            'FECHA_NAC' => 'date',
        ];
    }

    public function villa(): BelongsTo
    {
        return $this->belongsTo(Villa::class, 'CLV_CLIE', 'CLV_CLIE');
    }
}
