<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Movimiento;
use App\Support\NombreArchivo;
use Barryvdh\DomPDF\Facade\Pdf;

class ReciboController extends Controller
{
    public function pdf(Movimiento $movimiento)
    {
        $movimiento->load(['villa', 'concepto', 'formaPago', 'usuario']);

        $pdf = Pdf::loadView('recibos.movimiento', [
            'titulo' => 'Recibo de '.($movimiento->concepto->ES_CARGO ? 'Cargo' : 'Crédito / Abono'),
            'movimiento' => $movimiento,
            'usuario' => auth()->user()?->name ?? 'Sistema',
        ]);

        // recibo-<folio>_<villa>_<fecha del movimiento>.pdf
        return $pdf->stream(NombreArchivo::armar(
            'recibo-'.($movimiento->FOLIO ?? $movimiento->ID_MOV),
            $movimiento->CLV_CLIE,
            NombreArchivo::fecha($movimiento->FECHA_APLI),
            'pdf',
        ));
    }
}
