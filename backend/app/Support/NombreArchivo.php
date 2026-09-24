<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Nombre de los archivos exportados (PDF y Excel): `titulodocumento_villa_fecha.ext`.
 *
 *   estado-de-cuenta_B-1_01-02-2026-al-21-09-2026.pdf   (una villa)
 *   estado-de-cuenta_todas_01-02-2026-al-21-09-2026.xlsx (todas las villas)
 *   recibo-CR0000141_B-1_21-09-2026.pdf                  (recibo de un movimiento)
 *   saldos-generales_21-09-2026.pdf                      (sin villa: el segmento se omite)
 *
 * Las fechas van como DD-MM-AAAA porque la barra de DD/MM/AAAA no se admite en un nombre de archivo.
 */
class NombreArchivo
{
    public static function armar(string $titulo, ?string $villa, string $fecha, string $extension): string
    {
        $partes = [self::limpiar($titulo)];

        if ($villa !== null && $villa !== '') {
            $partes[] = self::limpiar($villa);
        }

        $partes[] = $fecha;

        return implode('_', $partes).".{$extension}";
    }

    /** DD-MM-AAAA */
    public static function fecha(CarbonInterface $fecha): string
    {
        return $fecha->format('d-m-Y');
    }

    /** DD-MM-AAAA-al-DD-MM-AAAA */
    public static function rango(CarbonInterface $desde, CarbonInterface $hasta): string
    {
        return self::fecha($desde).'-al-'.self::fecha($hasta);
    }

    /** Solo letras, numeros y guiones (sin acentos ni espacios); conserva mayusculas, ej. "B-1" o "CR0000141". */
    private static function limpiar(string $texto): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', Str::ascii($texto)), '-');
    }
}
