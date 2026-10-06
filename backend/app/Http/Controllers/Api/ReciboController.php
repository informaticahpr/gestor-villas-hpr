<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movimiento;
use App\Support\NombreArchivo;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Documento de un movimiento, siempre en original y copia: "Nota de Cargo" para los cargos (no se
 * imprime al registrarlo; se consulta desde los movimientos) y "Recibo" para los creditos/abonos
 * (se abre para imprimir en cuanto se registra).
 */
class ReciboController extends Controller
{
    public function pdf(Movimiento $movimiento)
    {
        $movimiento->load(['villa', 'concepto', 'formaPago', 'usuario', 'anuladoPor']);
        $esCargo = (bool) $movimiento->concepto->ES_CARGO;

        $pdf = Pdf::loadView('recibos.movimiento', [
            'titulo' => $esCargo ? 'Nota de Cargo' : 'Recibo de Crédito / Abono',
            'movimiento' => $movimiento,
            'usuario' => auth()->user()?->name ?? 'Sistema',
            'subtitulo' => 'Administración de Villas · Tel. 9460-2996',
        ]);

        // nota-de-cargo-<correlativo>_<villa>_<fecha>.pdf o recibo-<correlativo>_<villa>_<fecha>.pdf
        return $pdf->stream(NombreArchivo::armar(
            ($esCargo ? 'nota-de-cargo-' : 'recibo-').($movimiento->CORRELATIVO ?? $movimiento->ID_MOV),
            $movimiento->CLV_CLIE,
            NombreArchivo::fecha($movimiento->FECHA_APLI),
            'pdf',
        ));
    }
}
