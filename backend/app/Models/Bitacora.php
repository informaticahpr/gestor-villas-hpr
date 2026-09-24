<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bitacora extends Model
{
    protected $table = 'bitacora';

    public $timestamps = false;

    protected $fillable = ['usuario_id', 'entidad', 'accion', 'descripcion', 'created_at'];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * withTrashed() para que un registro viejo siga mostrando el nombre del
     * usuario aunque ese usuario ya haya sido "eliminado" (soft delete).
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id')->withTrashed();
    }

    /**
     * Registra una entrada de bitacora asociada al usuario autenticado actual.
     */
    public static function registrar(string $entidad, string $accion, string $descripcion): void
    {
        static::create([
            'usuario_id' => auth()->id(),
            'entidad' => $entidad,
            'accion' => $accion,
            'descripcion' => $descripcion,
            // hora de la app (America/Tegucigalpa); el DEFAULT de la columna (CURRENT_TIMESTAMP)
            // guarda UTC en SQLite y se leeria 6 horas desfasada
            'created_at' => now(),
        ]);
    }
}
