<?php

namespace App\Services;

use App\Models\Correlativo;
use Illuminate\Support\Facades\DB;

class CorrelativoService
{
    /**
     * Asigna el siguiente correlativo disponible para un cargo (CA0000001...) o un
     * credito/abono (CR0000001...), incrementando el contador correspondiente
     * dentro de una transaccion con bloqueo de fila para evitar correlativos duplicados.
     */
    public function siguiente(bool $esCargo): string
    {
        return DB::transaction(function () use ($esCargo) {
            $tipo = $esCargo ? 'CA' : 'CR';
            $contador = Correlativo::where('tipo', $tipo)->lockForUpdate()->firstOrFail();
            $numero = $contador->siguiente;
            $contador->increment('siguiente');

            return sprintf('%s%07d', $tipo, $numero);
        });
    }
}
