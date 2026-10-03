<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Busqueda por texto que ignora mayusculas y acentos: encuentra "Sanchez" aunque el nombre este
 * guardado como "SÁNCHEZ". Lo usan los buscadores de villas y de propietarios.
 */
trait BuscaSinAcentos
{
    /** Mapa de vocales/eñe acentuadas -> su forma simple. */
    private const MAPA_ACENTOS = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
    ];

    private function sinAcentos(string $texto): string
    {
        return strtolower(strtr($texto, self::MAPA_ACENTOS));
    }

    /**
     * Expresion SQL que aplica el mismo mapa de acentos a una columna, para
     * poder comparar contra un termino de busqueda ya normalizado.
     */
    private function columnaSinAcentos(string $columna): string
    {
        $expr = 'LOWER('.DB::getQueryGrammar()->wrap($columna).')';
        foreach (self::MAPA_ACENTOS as $con => $sin) {
            $expr = "REPLACE({$expr}, '{$con}', '{$sin}')";
        }

        return $expr;
    }

    /**
     * Igual que columnaSinAcentos(), pero ademas le quita los guiones a la
     * columna -- asi "A-1" hace match si el usuario busca "A1" sin guion.
     * Se usa para claves (numero de villa, DNI), no para nombres.
     */
    private function columnaClave(string $columna): string
    {
        return 'REPLACE('.$this->columnaSinAcentos($columna).", '-', '')";
    }
}
