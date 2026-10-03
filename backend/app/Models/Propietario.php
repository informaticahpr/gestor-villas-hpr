<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Dueño de una o varias villas. */
#[Fillable(['NOMBRES', 'APELLIDOS', 'DNI', 'TELF', 'CELULAR', 'OTRO_TEL', 'MAIL', 'MAIL2', 'FECHA_NAC'])]
class Propietario extends Model
{
    protected $table = 'propietarios';

    protected function casts(): array
    {
        return [
            'FECHA_NAC' => 'date',
        ];
    }

    public function villas(): HasMany
    {
        return $this->hasMany(Villa::class, 'PROPIETARIO_ID');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->NOMBRES} {$this->APELLIDOS}");
    }
}
