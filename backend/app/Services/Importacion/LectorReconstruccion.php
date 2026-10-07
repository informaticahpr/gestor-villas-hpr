<?php

namespace App\Services\Importacion;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Lee el Excel de reconstruccion de saldos (respaldo del sistema viejo TPVADMIN): una hoja por villa
 * ("A-1", "TH-13", "S-12B"...) con este formato:
 *   A1 "Villa A-1" / A2 "Propietario: ..." / fila 5 encabezado Fecha | Descripción | Cargos | Abonos | Saldo
 *   desde la fila 6 un movimiento por fila y al final una fila "TOTALES" con el saldo en la columna E.
 * La hoja "Resumen" trae el saldo reconstruido de cada villa (columna D) para cuadrar.
 * Las hojas "Hallazgos" y "Fechas corregidas" son notas: sus cambios ya estan aplicados en cada villa.
 */
class LectorReconstruccion
{
    private const HOJAS_DE_NOTAS = ['Resumen', 'Hallazgos', 'Fechas corregidas'];

    /**
     * @return array{villas: array<string, array{codigo: string, hoja: string, propietario: string, saldo_hoja: ?float,
     *     filas: list<array{fila: int, fecha: string, descripcion: string, cargo: float, abono: float, saldo: ?float}>}>,
     *     resumen: array<string, float>}
     */
    public function leer(string $archivo): array
    {
        $lector = IOFactory::createReaderForFile($archivo);
        $libro = $lector->load($archivo);

        $villas = [];
        foreach ($libro->getWorksheetIterator() as $ws) {
            $hoja = $ws->getTitle();
            if (in_array($hoja, self::HOJAS_DE_NOTAS, true)) {
                continue;
            }
            $codigo = strtoupper(trim($hoja)); // "z-69" -> "Z-69"
            $villa = [
                'codigo' => $codigo,
                'hoja' => $hoja,
                'propietario' => trim(preg_replace('/^propietario:\s*/iu', '', (string) $ws->getCell('A2')->getValue())),
                'saldo_hoja' => null,
                'filas' => [],
            ];
            $max = $ws->getHighestDataRow();
            for ($f = 6; $f <= $max; $f++) {
                $descripcion = trim(preg_replace('/\s+/u', ' ', (string) $ws->getCell("B{$f}")->getValue()));
                if (mb_strtoupper($descripcion) === 'TOTALES') {
                    $villa['saldo_hoja'] = $this->numero($ws->getCell("E{$f}"));
                    break;
                }
                $fecha = $ws->getCell("A{$f}")->getValue();
                if (! is_numeric($fecha)) {
                    if ($descripcion !== '') {
                        throw new \RuntimeException("Hoja {$hoja}, fila {$f}: movimiento sin fecha (\"{$descripcion}\").");
                    }

                    continue;
                }
                $villa['filas'][] = [
                    'fila' => $f,
                    'fecha' => Date::excelToDateTimeObject($fecha)->format('Y-m-d'),
                    'descripcion' => $descripcion,
                    'cargo' => $this->numero($ws->getCell("C{$f}")) ?? 0.0,
                    'abono' => $this->numero($ws->getCell("D{$f}")) ?? 0.0,
                    'saldo' => $this->numero($ws->getCell("E{$f}")),
                ];
            }
            $villas[$codigo] = $villa;
        }

        $resumen = [];
        if ($ws = $libro->getSheetByName('Resumen')) {
            for ($f = 1; $f <= $ws->getHighestDataRow(); $f++) {
                $codigo = strtoupper(trim((string) $ws->getCell("A{$f}")->getValue()));
                if (isset($villas[$codigo]) && ($saldo = $this->numero($ws->getCell("D{$f}"))) !== null) {
                    $resumen[$codigo] = $saldo;
                }
            }
        }
        $libro->disconnectWorksheets();

        return ['villas' => $villas, 'resumen' => $resumen];
    }

    /**
     * Valor numerico de la celda. Las formulas se leen con el valor que guardo Excel: las del Resumen
     * apuntan a hojas como 'A-1'!E127 y PhpSpreadsheet no siempre las puede recalcular.
     */
    private function numero(Cell $celda): ?float
    {
        $v = $celda->isFormula() ? $celda->getOldCalculatedValue() : $celda->getValue();

        return is_numeric($v) ? round((float) $v, 2) : null;
    }
}
