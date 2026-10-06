<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Contador del siguiente correlativo por tipo: CA (cargos) y CR (creditos/abonos). */
class Correlativo extends Model
{
    protected $table = 'correlativos';

    protected $primaryKey = 'tipo';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['tipo', 'siguiente'];
}
