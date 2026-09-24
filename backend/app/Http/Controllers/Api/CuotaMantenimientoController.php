<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Concepto;
use App\Support\Formato;
use Illuminate\Http\Request;

/**
 * Cuota de mantenimiento mensual: su monto se define aqui (Director/Admin, en Configuracion > Cuotas)
 * y se guarda en el MONTO_DEFAULT del concepto marcado ES_MANTENIMIENTO. Al aplicarla desde
 * Cargo / Credito nadie puede escribir otro valor (ver MovimientoController::montoParaConcepto()).
 */
class CuotaMantenimientoController extends Controller
{
    public function show()
    {
        return response()->json($this->serializar($this->concepto()));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'MONTO' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
        ], [
            'MONTO.required' => 'Debes indicar el monto de la cuota de mantenimiento.',
            'MONTO.numeric' => 'El monto debe ser un número.',
            'MONTO.gt' => 'El monto debe ser mayor a cero.',
            'MONTO.max' => 'El monto es demasiado grande.',
        ]);

        $concepto = $this->concepto();
        $anterior = $concepto->MONTO_DEFAULT !== null ? Formato::monto((float) $concepto->MONTO_DEFAULT) : 'sin definir';

        $concepto->update(['MONTO_DEFAULT' => $data['MONTO']]);

        Bitacora::registrar(
            'cuota_mantenimiento',
            'editar',
            'Cambió la cuota de mantenimiento mensual de '.$anterior.' a '.Formato::monto((float) $data['MONTO']).'.',
        );

        return response()->json($this->serializar($concepto));
    }

    private function concepto(): Concepto
    {
        return Concepto::mantenimiento()
            ?? abort(404, 'No existe el concepto de la cuota de mantenimiento.');
    }

    private function serializar(Concepto $concepto): array
    {
        return [
            'NUM_CPTO' => $concepto->NUM_CPTO,
            'DESCR' => $concepto->DESCR,
            'MONTO' => $concepto->MONTO_DEFAULT !== null ? (float) $concepto->MONTO_DEFAULT : null,
        ];
    }
}
