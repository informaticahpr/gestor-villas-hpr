<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Concepto;
use Illuminate\Http\Request;

class ConceptoController extends Controller
{
    public function index(Request $request)
    {
        $query = Concepto::orderBy('DESCR');

        if (! $request->boolean('incluir_inactivos')) {
            $query->where('ACTIVO', true);
        }

        return response()->json([
            'data' => $query->get(['NUM_CPTO', 'DESCR', 'ES_CARGO', 'ACTIVO', 'MONTO_DEFAULT', 'ES_MANTENIMIENTO'])
                ->map(fn (Concepto $c) => $this->serializar($c)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'DESCR' => ['required', 'string', 'max:40'],
            'ES_CARGO' => ['required', 'boolean'],
            'MONTO_DEFAULT' => ['nullable', 'numeric', 'min:0'],
        ], [
            'DESCR.required' => 'Debes escribir una descripción para el concepto.',
            'DESCR.max' => 'La descripción no puede superar los 40 caracteres.',
            'ES_CARGO.required' => 'Debes indicar si el concepto es un cargo o un abono.',
            'ES_CARGO.boolean' => 'El tipo de concepto no es válido.',
            'MONTO_DEFAULT.numeric' => 'El monto fijo debe ser un número.',
            'MONTO_DEFAULT.min' => 'El monto fijo no puede ser negativo.',
        ]);

        $concepto = Concepto::create($data + ['ACTIVO' => true]);

        Bitacora::registrar('concepto', 'crear', "Creó el concepto \"{$concepto->DESCR}\".");

        return response()->json(['concepto' => $this->serializar($concepto)], 201);
    }

    public function update(Request $request, Concepto $concepto)
    {
        $data = $request->validate([
            'DESCR' => ['required', 'string', 'max:40'],
            'ES_CARGO' => ['required', 'boolean'],
            'MONTO_DEFAULT' => ['nullable', 'numeric', 'min:0'],
        ], [
            'DESCR.required' => 'Debes escribir una descripción para el concepto.',
            'DESCR.max' => 'La descripción no puede superar los 40 caracteres.',
            'ES_CARGO.required' => 'Debes indicar si el concepto es un cargo o un abono.',
            'ES_CARGO.boolean' => 'El tipo de concepto no es válido.',
            'MONTO_DEFAULT.numeric' => 'El monto fijo debe ser un número.',
            'MONTO_DEFAULT.min' => 'El monto fijo no puede ser negativo.',
        ]);

        if ($concepto->ES_MANTENIMIENTO) {
            if (! $data['ES_CARGO']) {
                return response()->json(['message' => 'El concepto de la cuota de mantenimiento debe ser un cargo.'], 422);
            }
            // el monto de la cuota de mantenimiento solo se cambia en Configuracion > Cuotas
            if (($data['MONTO_DEFAULT'] ?? null) !== null && (float) $data['MONTO_DEFAULT'] !== (float) $concepto->MONTO_DEFAULT) {
                return response()->json(['message' => 'El monto de la cuota de mantenimiento se define en Configuración → Cuotas.'], 422);
            }
            unset($data['MONTO_DEFAULT']);
        }

        if ($data['ES_CARGO'] !== $concepto->ES_CARGO && $concepto->movimientos()->exists()) {
            return response()->json([
                'message' => 'No se puede cambiar el tipo (cargo/crédito) de un concepto que ya tiene movimientos aplicados.',
            ], 422);
        }

        $concepto->update($data);

        Bitacora::registrar('concepto', 'editar', "Editó el concepto \"{$concepto->DESCR}\".");

        return response()->json(['concepto' => $this->serializar($concepto)]);
    }

    public function activar(Request $request, Concepto $concepto)
    {
        $data = $request->validate([
            'ACTIVO' => ['required', 'boolean'],
        ]);

        if (! $data['ACTIVO'] && $concepto->ES_MANTENIMIENTO) {
            return response()->json([
                'message' => 'No se puede desactivar el concepto de la cuota de mantenimiento: es el que usa la cuota mensual.',
            ], 422);
        }

        if (! $data['ACTIVO'] && $concepto->movimientos()->exists()) {
            return response()->json([
                'message' => 'No se puede desactivar: este concepto ya tiene movimientos aplicados a una o más villas.',
            ], 422);
        }

        $concepto->update(['ACTIVO' => $data['ACTIVO']]);

        Bitacora::registrar('concepto', $data['ACTIVO'] ? 'activar' : 'desactivar', ($data['ACTIVO'] ? 'Activó' : 'Desactivó')." el concepto \"{$concepto->DESCR}\".");

        return response()->json(['concepto' => $this->serializar($concepto)]);
    }

    public function destroy(Concepto $concepto)
    {
        if ($concepto->ES_MANTENIMIENTO) {
            return response()->json([
                'message' => 'No se puede eliminar el concepto de la cuota de mantenimiento: es el que usa la cuota mensual.',
            ], 422);
        }

        if ($concepto->movimientos()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: este concepto ya tiene movimientos aplicados a una o más villas.',
            ], 422);
        }

        $descr = $concepto->DESCR;
        $concepto->delete();

        Bitacora::registrar('concepto', 'eliminar', "Eliminó el concepto \"{$descr}\".");

        return response()->json(status: 204);
    }

    private function serializar(Concepto $concepto): array
    {
        return [
            'NUM_CPTO' => $concepto->NUM_CPTO,
            'DESCR' => $concepto->DESCR,
            'ES_CARGO' => (bool) $concepto->ES_CARGO,
            'ACTIVO' => (bool) $concepto->ACTIVO,
            'MONTO_DEFAULT' => $concepto->MONTO_DEFAULT !== null ? (float) $concepto->MONTO_DEFAULT : null,
            'ES_MANTENIMIENTO' => (bool) $concepto->ES_MANTENIMIENTO,
        ];
    }
}
