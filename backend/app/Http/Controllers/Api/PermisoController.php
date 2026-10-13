<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Role;
use App\Models\RolPermiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Configuracion -> Permisos (solo el Administrador): que puede hacer cada cargo. */
class PermisoController extends Controller
{
    public function index()
    {
        return response()->json([
            'catalogo' => collect(RolPermiso::CATALOGO)->map(fn ($p, $clave) => [
                'clave' => $clave,
                'grupo' => $p[0],
                'etiqueta' => $p[1],
                'descripcion' => $p[2],
            ])->values(),
            'roles' => Role::with('permisos')->orderBy('id')->get()->map(fn (Role $r) => [
                'id' => $r->id,
                'nombre' => $r->nombre,
                'permisos' => $r->clavesDePermisos(),
                // el Administrador siempre lo tiene todo
                'editable' => $r->nombre !== Role::ADMIN,
            ]),
        ]);
    }

    /** Reemplaza los permisos del cargo por los que llegan marcados. */
    public function update(Request $request, Role $rol)
    {
        if ($rol->nombre === Role::ADMIN) {
            return response()->json([
                'message' => 'El Administrador siempre tiene todos los permisos; no se le pueden quitar.',
            ], 422);
        }

        $data = $request->validate([
            'permisos' => ['present', 'array'],
            'permisos.*' => ['string', 'distinct', Rule::in(array_keys(RolPermiso::CATALOGO))],
        ], [
            'permisos.*.in' => 'Uno de los permisos no es válido.',
        ]);

        $antes = $rol->load('permisos')->clavesDePermisos();
        $despues = array_values(array_intersect(array_keys(RolPermiso::CATALOGO), $data['permisos']));
        $dados = array_diff($despues, $antes);
        $quitados = array_diff($antes, $despues);

        if ($dados || $quitados) {
            DB::transaction(function () use ($rol, $despues) {
                $rol->permisos()->delete();
                $rol->permisos()->createMany(array_map(fn ($p) => ['permiso' => $p], $despues));
            });

            $etiquetas = fn (array $claves) => implode(', ', array_map(fn ($c) => RolPermiso::CATALOGO[$c][1], $claves));
            $cambios = array_filter([
                $dados ? 'le dio: '.$etiquetas($dados) : null,
                $quitados ? 'le quitó: '.$etiquetas($quitados) : null,
            ]);
            Bitacora::registrar('permiso', 'editar', "Cambió los permisos del cargo {$rol->nombre}; ".implode('; ', $cambios).'.');
        }

        return response()->json(['permisos' => $despues]);
    }
}
