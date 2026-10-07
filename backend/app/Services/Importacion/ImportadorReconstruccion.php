<?php

namespace App\Services\Importacion;

use Carbon\Carbon;

/**
 * Convierte el Excel de reconstruccion de saldos (LectorReconstruccion) en el mismo "plan" que usa
 * ImportadorEstadosCuenta, asi se aplica en la base local o se genera el script para Railway igual.
 *
 * Reglas (acordadas con el usuario):
 * - Los saldos correctos son los de la reconstruccion: se carga cada movimiento de la hoja de la villa
 *   con su fecha (desde el "Saldo Inicial" de 2021), sin fecha de corte. La suma debe dar el saldo de
 *   la hoja y el del Resumen; si no, la villa se marca para revisar y no se importa nada.
 * - El propietario actual es el de la reconstruccion; los anteriores salen del Excel viejo y vienen en
 *   un CSV revisado (villa;propietario;desde), en orden: el ultimo de cada villa es el actual.
 */
class ImportadorReconstruccion
{
    public function __construct(private ImportadorEstadosCuenta $importador) {}

    /**
     * @param  array{villas: array, resumen: array}  $libro  de LectorReconstruccion::leer()
     * @param  array<string, list<array{propietario: string, desde: ?string}>>  $propietarios  por villa, del mas antiguo al actual
     * @param  string  $hastaDesconocido  fecha "hasta" en el historial cuando no se sabe cuando cambio el propietario
     */
    public function planificar(array $libro, array $propietarios, string $hastaDesconocido): array
    {
        $avisos = [];
        $villas = [];
        $catalogo = [];
        $codigos = array_keys($libro['villas']);
        usort($codigos, 'strnatcmp');

        foreach ($codigos as $codigo) {
            $hoja = $libro['villas'][$codigo];
            $villa = $this->planVilla($hoja, $libro['resumen'][$codigo] ?? null);

            $lista = $propietarios[$codigo] ?? [];
            if (! $lista) {
                $lista = [['propietario' => $hoja['propietario'], 'desde' => null]];
                $villa['avisos'][] = 'No está en el CSV de propietarios: se usa el nombre de la reconstrucción.';
            } elseif (NombrePropietario::clave(end($lista)['propietario']) !== NombrePropietario::clave($hoja['propietario'])) {
                $villa['avisos'][] = 'El propietario actual del CSV ("'.end($lista)['propietario'].'") no es el de la reconstrucción ("'.$hoja['propietario'].'").';
            }
            $villas[$codigo] = $this->importador->asignarPropietarios($villa, $lista, $hastaDesconocido, $catalogo);
        }

        foreach (array_diff(array_keys($propietarios), $codigos) as $sinHoja) {
            $avisos[] = "{$sinHoja}: está en el CSV de propietarios pero no tiene hoja en el Excel.";
        }

        $primera = min(array_map(fn ($v) => $v['movimientos'][0]['fecha'] ?? '9999-12-31', $villas));

        return [
            'corte' => $primera,
            'villas' => $villas,
            'propietarios' => $this->importador->nombresDelCatalogo($catalogo),
            'avisos' => $avisos,
            // sprintf: villas, propietarios, movimientos
            'bitacora' => 'Importó del Excel de reconstrucción de saldos (06/10/2026) %d villas, %d propietarios y %d movimientos.',
        ];
    }

    private function planVilla(array $hoja, ?float $saldoResumen): array
    {
        $avisos = [];
        $movs = [];
        $saldo = 0.0;
        $descuadres = 0;
        foreach ($hoja['filas'] as $f) {
            foreach ([[$f['cargo'], true], [$f['abono'], false]] as [$monto, $esCargo]) {
                if (abs($monto) < 0.005) {
                    continue;
                }
                // un monto negativo en "Cargos" es en realidad un credito (y viceversa)
                $cargo = $monto > 0 ? $esCargo : ! $esCargo;
                $movs[] = [
                    'fecha' => $f['fecha'],
                    'concepto' => $cargo ? $this->conceptoCargo($f['descripcion']) : 'Abono / Pago',
                    'forma' => $cargo ? null : $this->formaPago($f['descripcion']),
                    'importe' => abs($monto),
                    'obs' => mb_substr($f['descripcion'] !== '' ? $f['descripcion'] : "Reconstrucción de saldos (fila {$f['fila']})", 0, 255),
                ];
                $saldo = round($saldo + ($cargo ? abs($monto) : -abs($monto)), 2);
            }
            if ($f['saldo'] !== null && abs($f['saldo'] - $saldo) >= 0.01) {
                $descuadres++;
            }
        }

        if ($descuadres) {
            $avisos[] = "{$descuadres} fila(s) donde la suma no da el saldo de la columna E.";
        }
        foreach (['de la hoja (TOTALES)' => $hoja['saldo_hoja'], 'del Resumen' => $saldoResumen] as $donde => $esperado) {
            if ($esperado === null) {
                $avisos[] = "No se encontró el saldo {$donde}.";
            } elseif (abs($esperado - $saldo) >= 0.01) {
                $avisos[] = 'La suma de los movimientos ('.number_format($saldo, 2).") no da el saldo {$donde} (".number_format($esperado, 2).').';
            }
        }
        $futuros = count(array_filter($movs, fn ($m) => $m['fecha'] > Carbon::today()->toDateString()));
        if ($futuros) {
            $avisos[] = "{$futuros} movimiento(s) con fecha futura: el sistema los cuenta en el saldo cuando llegue su fecha.";
        }

        return [
            'codigo' => $hoja['codigo'],
            'dir' => 'BLOQUE '.explode('-', $hoja['codigo'])[0],
            'titulo' => $hoja['propietario'],
            'hoja' => $hoja['hoja'],
            'saldo_final' => $saldo,
            'saldo_excel' => $saldoResumen ?? $hoja['saldo_hoja'],
            'movimientos' => $movs,
            'avisos' => $avisos,
            'cuadra' => ! $descuadres && $hoja['saldo_hoja'] !== null && abs($hoja['saldo_hoja'] - $saldo) < 0.01
                && $saldoResumen !== null && abs($saldoResumen - $saldo) < 0.01,
        ];
    }

    /**
     * "Cuota de mantenimiento may-2021 (A260)" -> cuota de mantenimiento; "Luz ..." -> Energia Electrica;
     * "Saldo Inicial (A1)" / "Saldo anterior" -> Saldo inicial; el resto (Manejo de Villa, Reparaciones,
     * Limpieza, Reconexion de agua, Impuesto Municipal, Internet, Factura...) -> Cargo extraordinario,
     * con la descripcion completa en la observacion.
     */
    private function conceptoCargo(string $descripcion): string
    {
        $d = mb_strtolower($descripcion);

        return match (true) {
            (bool) preg_match('/^saldo (inicial|anterior)/u', $d) => ImportadorEstadosCuenta::SALDO_INICIAL,
            (bool) preg_match('/^cuota de mantenimiento/u', $d) => ImportadorEstadosCuenta::MANTENIMIENTO,
            (bool) preg_match('/^luz\b|energ[ií]a|\benee\b/u', $d) => ImportadorEstadosCuenta::ENERGIA,
            (bool) preg_match('/\bmora\b|recargo/u', $d) => 'Mora',
            default => 'Cargo extraordinario',
        };
    }

    /** Forma de pago segun como empieza la descripcion ("Deposito Bancario – Recibo R6702 – ..."). */
    private function formaPago(string $descripcion): ?string
    {
        $d = mb_strtolower($descripcion);

        return match (true) {
            (bool) preg_match('/^dep[oó]sito/u', $d) => 'Depósito',
            (bool) preg_match('/^efectivo/u', $d) => 'Efectivo',
            (bool) preg_match('/^cheque/u', $d) => 'Cheque',
            (bool) preg_match('/^canje/u', $d) => 'Canje',
            (bool) preg_match('/^transf/u', $d) => 'Transferencia',
            default => null, // tarjeta de credito, compensacion de alquileres, notas de credito, anticipos...
        };
    }
}
