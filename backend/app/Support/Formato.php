<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Formatos de presentacion compartidos por los reportes (mismos que el frontend:
 * 32000 -> "$32,000.00", -1000 -> "-$1,000.00").
 */
class Formato
{
    public static function monto(float|int|null $valor): string
    {
        $valor = (float) $valor;

        return ($valor < 0 ? '-' : '').'$'.number_format(abs($valor), 2);
    }

    /** Numero sin ceros sobrantes: 0.83 -> "0.83", 1.50 -> "1.5", 2.00 -> "2". */
    public static function numero(float|int $valor, int $decimales = 2): string
    {
        return rtrim(rtrim(number_format((float) $valor, $decimales, '.', ','), '0'), '.');
    }

    public static function fecha(DateTimeInterface|string|null $fecha): string
    {
        return $fecha ? Carbon::parse($fecha)->format('d/m/Y') : '';
    }
}
