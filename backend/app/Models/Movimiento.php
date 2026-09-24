<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'CLV_CLIE', 'NUM_CPTO', 'FORMA_PAGO_ID', 'IMPORTE', 'FECHA_APLI', 'FECHA_VENC',
    'ANIO', 'MES', 'REFER', 'OBS', 'USUARIO_ID', 'FOLIO',
])]
class Movimiento extends Model
{
    protected $table = 'CUEN1';

    protected $primaryKey = 'ID_MOV';

    protected function casts(): array
    {
        return [
            'IMPORTE' => 'decimal:2',
            'FECHA_APLI' => 'date',
            'FECHA_VENC' => 'date',
        ];
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
}
