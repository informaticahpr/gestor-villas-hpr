<?php

namespace App\Console\Commands;

use App\Services\Importacion\ImportadorEstadosCuenta;
use App\Services\Importacion\ImportadorReconstruccion;
use App\Services\Importacion\LectorReconstruccion;
use Illuminate\Console\Command;

/**
 * Importa el Excel de reconstruccion de saldos (una hoja por villa). Ver ImportadorReconstruccion.
 *
 * Sin opciones solo muestra el reporte (no toca la base). Uso tipico:
 *   php artisan villas:importar-reconstruccion "<excel>" --propietarios="<csv>"                          (revisar)
 *   php artisan villas:importar-reconstruccion "<excel>" --propietarios="<csv>" --aplicar --reemplazar   (base local)
 *   php artisan villas:importar-reconstruccion "<excel>" --propietarios="<csv>" --sql=salida.sql          (script para Railway)
 *
 * El CSV de propietarios (separado por ";") tiene las columnas villa;propietario;desde[;nota], con una
 * fila por propietario EN ORDEN: los anteriores primero y el actual al final (desde en DD/MM/AAAA o
 * AAAA-MM-DD, vacio si no se sabe).
 */
class ImportarReconstruccion extends Command
{
    protected $signature = 'villas:importar-reconstruccion
        {archivo : Ruta del Excel de reconstruccion (.xlsx)}
        {--propietarios= : CSV revisado con villa;propietario;desde (anteriores primero, actual al final)}
        {--hasta-desconocido=2025-02-19 : Fecha "hasta" del historial cuando no se sabe cuando cambio el propietario}
        {--aplicar : Carga los datos en la base de datos actual}
        {--reemplazar : Con --aplicar, borra antes todas las villas, propietarios, historial y movimientos}
        {--sql= : Escribe el script para PostgreSQL (Railway) en esta ruta}
        {--detalle= : Muestra los movimientos que se cargarian para esta villa (ej. A-1)}';

    protected $description = 'Importa el Excel de reconstruccion de saldos (villas, propietarios, historial y movimientos)';

    public function handle(LectorReconstruccion $lector, ImportadorReconstruccion $planificador, ImportadorEstadosCuenta $importador): int
    {
        ini_set('memory_limit', '4G');
        set_time_limit(0);

        $archivo = $this->argument('archivo');
        if (! is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");

            return self::FAILURE;
        }
        $propietarios = [];
        if ($this->option('propietarios')) {
            $propietarios = $this->leerPropietarios($this->option('propietarios'));
        } else {
            $this->warn('Sin --propietarios: se usa el nombre de cada hoja y no habrá historial de propietarios anteriores.');
        }

        $this->info('Leyendo el Excel (puede tardar un par de minutos)...');
        $plan = $planificador->planificar($lector->leer($archivo), $propietarios, $this->option('hasta-desconocido'));

        $this->reporte($plan);
        if ($detalle = $this->option('detalle')) {
            $this->detalleVilla($plan, strtoupper($detalle));
        }

        $sinCuadrar = array_keys(array_filter($plan['villas'], fn ($v) => ! $v['cuadra']));
        if ($sinCuadrar && ($this->option('sql') || $this->option('aplicar'))) {
            $this->error('No se importa nada: estas villas no cuadran con el Excel: '.implode(', ', $sinCuadrar));

            return self::FAILURE;
        }
        if ($ruta = $this->option('sql')) {
            file_put_contents($ruta, $importador->sql($plan));
            $this->info("Script SQL para Railway escrito en: {$ruta}");
        }
        if ($this->option('aplicar')) {
            $importador->aplicar($plan, (bool) $this->option('reemplazar'));
            $this->info('Datos cargados en la base actual ('.config('database.default').').');
        }

        return self::SUCCESS;
    }

    /** @return array<string, list<array{propietario: string, desde: ?string}>> en el orden del archivo */
    private function leerPropietarios(string $ruta): array
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($ruta)); // BOM de Excel
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252'); // CSV guardado por Excel en Windows
        }
        $lineas = array_values(array_filter(preg_split('/\r\n|\n|\r/', $contenido), fn ($l) => trim($l, " ;,\t") !== ''));

        $resultado = [];
        foreach (array_slice($lineas, 1) as $n => $linea) {
            [$villa, $nombre, $desde] = array_map('trim', str_getcsv($linea, ';', '"', '')) + [null, null, null];
            if (! $villa || ! $nombre) {
                continue;
            }
            $villa = strtoupper(preg_replace('/^([A-Z]+)\s*-?\s*(\d+[A-Z]?)$/i', '$1-$2', $villa));
            $resultado[$villa][] = ['propietario' => $nombre, 'desde' => $this->fecha($desde, $n + 2)];
        }

        return $resultado;
    }

    private function fecha(?string $texto, int $linea): ?string
    {
        if (! $texto) {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $texto, $m) || preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $texto, $m)) {
            [$a, $mes, $d] = strlen($m[1]) === 4 ? [$m[1], $m[2], $m[3]] : [$m[3], $m[2], $m[1]];

            return sprintf('%04d-%02d-%02d', $a, $mes, $d);
        }
        throw new \RuntimeException("Fecha no válida en la línea {$linea} del CSV de propietarios: \"{$texto}\" (usa AAAA-MM-DD o DD/MM/AAAA).");
    }

    private function reporte(array $plan): void
    {
        $filas = [];
        foreach ($plan['villas'] as $v) {
            $p = $plan['propietarios'][$v['propietario']];
            $filas[] = [
                $v['codigo'],
                mb_substr($p['NOMBRES'], 0, 22),
                mb_substr($p['APELLIDOS'], 0, 22),
                count($v['anteriores']) ?: '',
                $v['observacion'] ? 'sí' : '',
                count($v['movimientos']),
                number_format($v['saldo_final'], 2),
                number_format((float) $v['saldo_excel'], 2),
                $v['cuadra'] ? 'OK' : 'REVISAR',
            ];
        }
        $this->table(['Villa', 'Nombres', 'Apellidos', 'Anteriores', 'Obs.', 'Movs', 'Saldo', 'Saldo Excel', ''], $filas);

        $movs = array_sum(array_map(fn ($v) => count($v['movimientos']), $plan['villas']));
        $this->line(sprintf('%d villas, %d propietarios, %d movimientos, saldo total %s.',
            count($plan['villas']), count($plan['propietarios']), $movs, number_format(array_sum(array_column($plan['villas'], 'saldo_final')), 2)));

        $avisos = $plan['avisos'];
        foreach ($plan['propietarios'] as $p) {
            if (count($p['variantes']) > 1) {
                $avisos[] = "Mismo propietario escrito distinto, queda como \"{$p['NOMBRES']} {$p['APELLIDOS']}\": "
                    .implode('; ', array_map(fn ($nombre, $villas) => str_replace('|', ' ', $nombre).' ('.implode(', ', $villas).')', array_keys($p['variantes']), $p['variantes']));
            }
        }
        foreach ($plan['villas'] as $v) {
            foreach ($v['avisos'] as $a) {
                $avisos[] = "{$v['codigo']}: {$a}";
            }
        }
        if ($avisos) {
            $this->newLine();
            $this->warn('Avisos:');
            foreach ($avisos as $a) {
                $this->line("  - {$a}");
            }
        }
    }

    private function detalleVilla(array $plan, string $codigo): void
    {
        $v = $plan['villas'][$codigo] ?? null;
        if (! $v) {
            $this->error("No hay datos para la villa {$codigo}.");

            return;
        }
        $this->line('Observación: '.($v['observacion'] ?? '—'));
        foreach ($v['anteriores'] as $a) {
            $this->line("Anterior: {$a['NOMBRES']} {$a['APELLIDOS']} (desde ".($a['desde'] ?? '?')." hasta {$a['hasta']})");
        }
        $saldo = 0;
        $abonos = ['Abono / Pago'];
        $this->table(['Fecha', 'Concepto', 'Forma', 'Cargo', 'Crédito', 'Saldo', 'Observación'], array_map(function ($m) use (&$saldo, $abonos) {
            $esCargo = ! in_array($m['concepto'], $abonos, true);
            $saldo += $esCargo ? $m['importe'] : -$m['importe'];

            return [$m['fecha'], match ($m['concepto']) {
                ImportadorEstadosCuenta::MANTENIMIENTO => 'Cuota de mantenimiento', ImportadorEstadosCuenta::ENERGIA => 'Energía Eléctrica', default => $m['concepto']
            }, $m['forma'] ?? '',
                $esCargo ? number_format($m['importe'], 2) : '', $esCargo ? '' : number_format($m['importe'], 2), number_format($saldo, 2), mb_substr($m['obs'], 0, 60)];
        }, $v['movimientos']));
    }
}
