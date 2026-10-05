<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Valores configurables del sistema (clave -> valor), ej. el porcentaje de mora. */
#[Fillable(['clave', 'valor'])]
class Parametro extends Model
{
    public const MORA_PORCENTAJE = 'mora_porcentaje';

    protected $table = 'parametros';

    protected $primaryKey = 'clave';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function valor(string $clave, ?string $porDefecto = null): ?string
    {
        return static::find($clave)?->valor ?? $porDefecto;
    }

    public static function guardar(string $clave, ?string $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }

    /** Porcentaje de mora mensual (ej. 0.83 = 0.83 %). */
    public static function porcentajeMora(): float
    {
        return (float) static::valor(self::MORA_PORCENTAJE, '0.83');
    }
}
