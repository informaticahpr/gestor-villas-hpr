<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;

/**
 * Fecha y hora del servidor en la zona horaria de la app (America/Tegucigalpa). El frontend la usa
 * como "hoy" en vez de calcularla con el reloj del navegador, que trabaja en UTC y despues de las
 * 6 pm de Tegucigalpa ya marca el dia siguiente.
 */
class HoyController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'hoy' => Carbon::today()->toDateString(),
            'ahora' => now()->toIso8601String(),
            'zona' => config('app.timezone'),
        ]);
    }
}
