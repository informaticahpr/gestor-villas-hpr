<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'descripcion'])]
class Role extends Model
{
    public $timestamps = false;

    public const DIRECTOR = 'Director';
    // siempre tiene todos los permisos; no se le pueden quitar (Configuracion -> Permisos)
    public const ADMIN = 'Admin';
    public const SUPERVISOR = 'Supervisor';
    // por defecto solo lectura: villas, estado de cuenta por villa y reimpresion
    public const CONSULTOR = 'Consultor';

    public function permisos(): HasMany
    {
        return $this->hasMany(RolPermiso::class, 'rol_id');
    }

    /** @return list<string> */
    public function clavesDePermisos(): array
    {
        if ($this->nombre === self::ADMIN) {
            return array_keys(RolPermiso::CATALOGO);
        }

        // solo las que siguen en el catalogo (por si alguna se retira del codigo)
        return array_values(array_intersect(array_keys(RolPermiso::CATALOGO), $this->permisos->pluck('permiso')->all()));
    }
}
