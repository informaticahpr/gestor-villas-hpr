<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\FormaPago;
use Illuminate\Http\Request;

class FormaPagoController extends Controller
{
    public function index(Request $request)
    {
        $query = FormaPago::orderBy('nombre');

        if (! $request->boolean('incluir_inactivos')) {
            $query->where('activo', true);
        }

        return response()->json([
            'data' => $query->get(['id', 'nombre', 'activo']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:40', 'unique:formas_pago,nombre'],
        ], [
            'nombre.required' => 'Debes escribir un nombre para la forma de pago.',
            'nombre.max' => 'El nombre no puede superar los 40 caracteres.',
            'nombre.unique' => 'Ya existe una forma de pago con ese nombre.',
        ]);

        $formaPago = FormaPago::create($data + ['activo' => true]);

        Bitacora::registrar('forma_pago', 'crear', "Creó la forma de pago \"{$formaPago->nombre}\".");

        return response()->json(['forma_pago' => $formaPago], 201);
    }

    public function update(Request $request, FormaPago $formaPago)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:40', 'unique:formas_pago,nombre,'.$formaPago->id],
        ], [
            'nombre.required' => 'Debes escribir un nombre para la forma de pago.',
            'nombre.max' => 'El nombre no puede superar los 40 caracteres.',
            'nombre.unique' => 'Ya existe una forma de pago con ese nombre.',
        ]);

        $formaPago->update($data);

        Bitacora::registrar('forma_pago', 'editar', "Editó la forma de pago \"{$formaPago->nombre}\".");

        return response()->json(['forma_pago' => $formaPago]);
    }

    public function activar(Request $request, FormaPago $formaPago)
    {
        $data = $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        if (! $data['activo'] && $formaPago->movimientos()->exists()) {
            return response()->json([
                'message' => 'No se puede desactivar: esta forma de pago ya se usó en movimientos de una o más villas.',
            ], 422);
        }

        $formaPago->update(['activo' => $data['activo']]);

        Bitacora::registrar('forma_pago', $data['activo'] ? 'activar' : 'desactivar', ($data['activo'] ? 'Activó' : 'Desactivó')." la forma de pago \"{$formaPago->nombre}\".");

        return response()->json(['forma_pago' => $formaPago]);
    }

    public function destroy(FormaPago $formaPago)
    {
        if ($formaPago->movimientos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: esta forma de pago ya se usó en movimientos de una o más villas.',
            ], 422);
        }

        $nombre = $formaPago->nombre;
        $formaPago->delete();

        Bitacora::registrar('forma_pago', 'eliminar', "Eliminó la forma de pago \"{$nombre}\".");

        return response()->json(status: 204);
    }
}
