<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Movimiento;
use App\Models\Parametro;
use App\Models\Villa;
use App\Services\MoraService;
use App\Services\SaldoService;
use App\Support\Formato;
use Illuminate\Http\Request;

/**
 * Mora mensual: porcentaje configurable y arreglos de pago (anular moras de una villa). Todo para
 * Director/Admin (ver routes/api.php). La aplicacion de la mora es automatica (moras:aplicar).
 */
class MoraController extends Controller
{
    public function __construct(
        private readonly MoraService $moras,
        private readonly SaldoService $saldos,
    ) {}

    public function configuracion()
    {
        return response()->json(['porcentaje' => Parametro::porcentajeMora(), 'dia' => MoraService::DIA_APLICACION]);
    }

    public function actualizarConfiguracion(Request $request)
    {
        $data = $request->validate([
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'porcentaje.required' => 'Debes indicar el porcentaje de mora.',
            'porcentaje.numeric' => 'El porcentaje debe ser un número.',
            'porcentaje.min' => 'El porcentaje no puede ser negativo.',
            'porcentaje.max' => 'El porcentaje no puede ser mayor a 100.',
        ]);

        $anterior = Parametro::porcentajeMora();
        $nuevo = round((float) $data['porcentaje'], 4);
        Parametro::guardar(Parametro::MORA_PORCENTAJE, (string) $nuevo);
        Bitacora::registrar('mora', 'editar', 'Cambió el porcentaje de mora mensual de '.Formato::numero($anterior, 4).'% a '.Formato::numero($nuevo, 4).'%.');

        return $this->configuracion();
    }

    /** Moras vigentes de la villa, para el arreglo de pago. */
    public function morasDeVilla(string $villa)
    {
        $villa = Villa::findOrFail($villa);
        $moras = $this->moras->morasVigentes($villa);

        return response()->json([
            'saldo' => $this->saldos->saldoDeVilla($villa),
            'total_moras' => round((float) $moras->sum('IMPORTE'), 2),
            'moras' => $moras->map(fn (Movimiento $m) => [
                'id' => $m->ID_MOV,
                'folio' => $m->FOLIO,
                'fecha' => $m->FECHA_APLI->toDateString(),
                'importe' => (float) $m->IMPORTE,
                'descripcion' => $m->OBS,
            ]),
        ]);
    }

    /** Arreglo de pago: anula las moras elegidas de la villa (queda registrado en la bitacora). */
    public function arregloDePago(Request $request, string $villa)
    {
        $villa = Villa::findOrFail($villa);
        $data = $request->validate([
            'moras' => ['required', 'array', 'min:1'],
            'moras.*' => ['integer'],
            'motivo' => ['required', 'string', 'min:5', 'max:200'],
        ], [
            'moras.required' => 'Selecciona al menos una mora.',
            'moras.min' => 'Selecciona al menos una mora.',
            'motivo.required' => 'Describe el arreglo de pago.',
            'motivo.min' => 'Describe el arreglo de pago con un poco más de detalle.',
            'motivo.max' => 'El motivo no puede superar los 200 caracteres.',
        ]);

        $anuladas = $this->moras->anularPorArreglo($villa, $data['moras'], $request->user(), trim($data['motivo']));
        if ($anuladas->isEmpty()) {
            return response()->json(['message' => 'Las moras seleccionadas ya no están vigentes.'], 422);
        }

        $total = round((float) $anuladas->sum('IMPORTE'), 2);
        Bitacora::registrar('mora', 'anular', mb_substr("Arreglo de pago villa {$villa->CLV_CLIE}: anuló {$anuladas->count()} mora(s) por ".Formato::monto($total).'. Motivo: '.trim($data['motivo']), 0, 255));

        return response()->json([
            'anuladas' => $anuladas->count(),
            'total' => $total,
            'saldo_villa' => $this->saldos->saldoDeVilla($villa),
        ]);
    }
}
