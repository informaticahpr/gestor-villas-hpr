<?php

namespace App\Services;

use App\Models\FolioCounter;
use Illuminate\Support\Facades\DB;

class FolioService
{
    /**
     * Asigna el siguiente folio disponible para un cargo (CA0000001...) o un
     * credito/abono (CR0000001...), incrementando el contador correspondiente
     * dentro de una transaccion con bloqueo de fila para evitar folios duplicados.
     */
    public function siguiente(bool $esCargo): string
    {
        return DB::transaction(function () use ($esCargo) {
            $tipo = $esCargo ? 'CA' : 'CR';
            $contador = FolioCounter::where('tipo', $tipo)->lockForUpdate()->firstOrFail();
            $numero = $contador->siguiente;
            $contador->increment('siguiente');

            return sprintf('%s%07d', $tipo, $numero);
        });
    }
}
