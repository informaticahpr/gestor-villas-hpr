<?php

namespace App\Services\Importacion;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Lee el Excel historico de estados de cuenta (una hoja por lote: "Estados de Cuentas Villas A", ...).
 *
 * Formato de cada hoja: varios bloques, uno por villa, que empiezan con las lineas de titulo
 * "Villas Palma Real" / "Contabilidad - ..." / "Movimientos Abiertos - ...", luego una linea con el
 * nombre del propietario y la villa (ej. "Marieta Yolanda Miranda A1") y el encabezado
 * Documento | Fecha | Descripcion | Cargos (debe) | Abonos (haber) | Saldo. Debajo, una fila por
 * movimiento. En la columna A, ademas de numeros de documento (FAC, REC, PGO...), a veces hay notas
 * o el nombre de un nuevo propietario. Solo se leen las columnas A-F (G en adelante son notas).
 */
class LectorEstadosCuenta
{
    /**
     * @return list<array{hoja: string, fila: int, titulo: string, codigo: ?string, codigo_inferido: bool,
     *     filas: list<array{fila: int, a: ?string, fecha: ?string, descripcion: ?string, debe: ?float, haber: ?float, saldo: ?float}>}>
     */
    public function leer(string $archivo, array $letras): array
    {
        $bloques = [];
        foreach ($letras as $letra) {
            $hoja = "Estados de Cuentas Villas {$letra}";
            $lector = IOFactory::createReaderForFile($archivo);
            $lector->setLoadSheetsOnly([$hoja]);
            $lector->setReadFilter(new class implements IReadFilter
            {
                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return in_array($columnAddress, ['A', 'B', 'C', 'D', 'E', 'F'], true);
                }
            });
            $ws = $lector->load($archivo)->getSheetByName($hoja)
                ?? throw new \RuntimeException("No existe la hoja \"{$hoja}\" en el Excel.");

            array_push($bloques, ...$this->fusionarContinuaciones($this->bloquesDeHoja($ws, $hoja, $letra)));
            $ws->getParent()->disconnectWorksheets();
        }

        return $bloques;
    }

    /**
     * A veces debajo del titulo viejo de una villa (ej. "Waleska Pastor B10", de un propietario
     * anterior, cuya villa tiene otro bloque mas abajo) se siguieron anotando los movimientos de la
     * villa de ARRIBA. Se reconoce porque su primer movimiento continua exactamente el saldo del
     * bloque anterior: esos movimientos se pasan al bloque anterior y se deja constancia en "notas".
     */
    private function fusionarContinuaciones(array $bloques): array
    {
        $porCodigo = array_count_values(array_filter(array_column($bloques, 'codigo')));
        $resultado = [];
        foreach ($bloques as $bloque) {
            $anterior = $resultado ? $resultado[array_key_last($resultado)] : null;
            $conFecha = array_values(array_filter($bloque['filas'], fn ($f) => $f['fecha'] !== null && $f['saldo'] !== null));
            $ultimoAnterior = $anterior ? collect($anterior['filas'])->filter(fn ($f) => $f['fecha'] !== null && $f['saldo'] !== null)->last() : null;

            if ($anterior && $ultimoAnterior && $conFecha && ($porCodigo[$bloque['codigo']] ?? 0) > 1) {
                $primero = $conFecha[0];
                $saldoPrevio = round($primero['saldo'] - ($primero['debe'] ?? 0) + ($primero['haber'] ?? 0), 2);
                if (abs($saldoPrevio - $ultimoAnterior['saldo']) < 0.01 && $primero['fecha'] >= $ultimoAnterior['fecha']) {
                    $k = array_key_last($resultado);
                    $resultado[$k]['filas'] = array_merge($resultado[$k]['filas'], $bloque['filas']);
                    $resultado[$k]['notas'][] = "Los movimientos desde {$primero['fecha']} están bajo el título \"{$bloque['titulo']}\" (fila {$bloque['fila']}) "
                        ."pero continúan el saldo de {$anterior['codigo']}: se cargan en {$anterior['codigo']}.";

                    continue;
                }
            }
            $resultado[] = $bloque + ['notas' => []];
        }

        return $resultado;
    }

    private function bloquesDeHoja(Worksheet $ws, string $hoja, string $letra): array
    {
        $bloques = [];
        $actual = null;
        $esperandoTitulo = false;
        $codigoAnterior = null;
        $ultimoTexto = null; // ultima linea de solo texto en A (el nombre puede ir arriba del encabezado)
        $max = $ws->getHighestDataRow();

        $nuevoBloque = function (int $fila) use (&$actual, &$bloques, &$codigoAnterior, &$esperandoTitulo, $hoja) {
            if ($actual) {
                $bloques[] = $actual;
                $codigoAnterior = $actual['codigo'] ?? $codigoAnterior;
            }
            $actual = ['hoja' => $hoja, 'fila' => $fila, 'titulo' => '', 'codigo' => null, 'codigo_inferido' => false, 'filas' => []];
            $esperandoTitulo = true;
        };

        for ($f = 1; $f <= $max; $f++) {
            $a = $this->texto($ws, 'A', $f);
            $b = $this->valor($ws, 'B', $f);
            $c = $this->texto($ws, 'C', $f);
            $tieneFecha = is_string($b) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $b);
            $esEncabezado = (is_string($b) && preg_match('/^fecha$/i', $b)) || ($c !== null && preg_match('/^descripci[oó]n$/iu', $c));
            $esMovimiento = $tieneFecha && ! $esEncabezado;

            // Inicio de bloque: titulo "Villas Palma Real" (en una linea sin fecha: a veces el texto
            // aparece pegado en la columna A de un movimiento y no es un bloque nuevo)
            if ($a !== null && ! $tieneFecha && preg_match('/^villas palma real$/i', $a)) {
                $nuevoBloque($f);
                $ultimoTexto = null;

                continue;
            }
            // Algunas villas no traen ese titulo: su encabezado aparece a media villa anterior
            if ($esEncabezado && $actual && ! $esperandoTitulo && $actual['filas']) {
                $nuevoBloque($f);
                if ($a === null || preg_match('/^documentos?$/i', $a)) {
                    $a = $ultimoTexto; // el nombre va en la linea de arriba
                }
            }
            if (! $actual) {
                continue;
            }
            if ($a !== null && preg_match('/^(contabilidad|movimientos abiertos)/i', $a)) {
                continue;
            }
            if ($a !== null && ! $tieneFecha && $c === null && $this->numero($ws, 'D', $f) === null && $this->numero($ws, 'E', $f) === null) {
                $ultimoTexto = $a;
            }
            if ($esperandoTitulo && ! $esMovimiento) {
                // la linea con el nombre puede ser la del encabezado o la anterior ("Documento | Fecha | ...")
                if ($actual['titulo'] === '' && $a !== null && ! preg_match('/^documentos?$/i', $a)) {
                    $actual['titulo'] = $a;
                    $actual['codigo'] = $this->codigoVilla($a, $letra);
                }
                if ($esEncabezado) {
                    $esperandoTitulo = false;
                    $this->inferirCodigo($actual, $codigoAnterior, $letra);
                }

                continue;
            }
            if ($esperandoTitulo) {
                // bloque sin linea de encabezado: empieza directo con movimientos
                $esperandoTitulo = false;
                $this->inferirCodigo($actual, $codigoAnterior, $letra);
            }

            $actual['filas'][] = [
                'fila' => $f,
                'a' => $a,
                'fecha' => is_string($b) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $b) ? $b : null,
                'descripcion' => $c,
                'debe' => $this->numero($ws, 'D', $f),
                'haber' => $this->numero($ws, 'E', $f),
                'saldo' => $this->numero($ws, 'F', $f),
            ];
        }
        if ($actual) {
            $bloques[] = $actual;
        }

        return $bloques;
    }

    /** "Marieta Yolanda Miranda A1", "Robertp Michelleti B-13", "B7 La Constancia" -> "A-1", "B-13", "B-7". */
    private function codigoVilla(string $titulo, string $letra): ?string
    {
        if (preg_match_all('/(?<![A-Za-z])'.$letra.'\s*-?\s*(\d{1,2})(?!\d)/i', $titulo, $m)) {
            return $letra.'-'.(int) end($m[1]);
        }

        return null;
    }

    /** Titulo sin numero de villa (ej. "CARLOS ALBERTO LEDESMA"): se asume la siguiente a la del bloque anterior. */
    private function inferirCodigo(array &$bloque, ?string $codigoAnterior, string $letra): void
    {
        if (! $bloque['codigo']) {
            $bloque['codigo'] = $this->siguienteCodigo($codigoAnterior, $letra);
            $bloque['codigo_inferido'] = true;
        }
    }

    private function siguienteCodigo(?string $anterior, string $letra): ?string
    {
        return $anterior && preg_match('/-(\d+)$/', $anterior, $m) ? $letra.'-'.((int) $m[1] + 1) : null;
    }

    private function valor(Worksheet $ws, string $col, int $fila): mixed
    {
        $celda = $ws->getCell($col.$fila);
        $v = $celda->getCalculatedValue();
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v) && Date::isDateTime($celda)) {
            return Date::excelToDateTimeObject($v)->format('Y-m-d');
        }

        return is_string($v) ? trim(preg_replace('/\s+/u', ' ', $v)) : $v;
    }

    private function texto(Worksheet $ws, string $col, int $fila): ?string
    {
        $v = $this->valor($ws, $col, $fila);

        return is_string($v) && $v !== '' ? $v : null;
    }

    private function numero(Worksheet $ws, string $col, int $fila): ?float
    {
        $v = $ws->getCell($col.$fila)->getCalculatedValue();

        return is_numeric($v) ? round((float) $v, 2) : null;
    }
}
