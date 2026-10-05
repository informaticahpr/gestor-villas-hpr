<?php

namespace App\Console\Commands;

use App\Services\Importacion\ImportadorEstadosCuenta;
use App\Services\Importacion\LectorEstadosCuenta;
use Illuminate\Console\Command;

/**
 * Importa el Excel historico de estados de cuenta (ver ImportadorEstadosCuenta para las reglas).
 *
 * Sin opciones solo muestra el reporte (no toca la base). Uso tipico:
 *   php artisan villas:importar-estados "<excel>" --propietarios="<csv>"                 (revisar)
 *   php artisan villas:importar-estados "<excel>" --propietarios="<csv>" --aplicar       (cargar en la BD actual)
 *   php artisan villas:importar-estados "<excel>" --propietarios="<csv>" --sql=salida.sql (script para Railway)
 *
 * El CSV de propietarios (separado por ";" o ",") tiene las columnas villa;propietario;desde
 * (desde en AAAA-MM-DD o DD/MM/AAAA). Una fila por propietario; el de "desde" mas reciente es el actual.
 */
class ImportarEstadosCuenta extends Command
{
    protected $signature = 'villas:importar-estados
        {archivo : Ruta del Excel (.xls/.xlsx)}
        {--propietarios= : CSV revisado con villa;propietario;desde}
        {--hojas=A,B,C,D : Letras de las hojas "Estados de Cuentas Villas X" a importar}
        {--corte=2024-12-31 : Fecha del saldo inicial (los movimientos posteriores se cargan en detalle)}
        {--aplicar : Carga los datos en la base de datos actual}
        {--sql= : Escribe el script para PostgreSQL (Railway) en esta ruta}
        {--detalle= : Muestra los movimientos que se cargarian para esta villa (ej. A-1)}';

    protected $description = 'Importa los estados de cuenta del Excel historico (villas, propietarios y movimientos)';

    public function handle(LectorEstadosCuenta $lector, ImportadorEstadosCuenta $importador): int
    {
        ini_set('memory_limit', '4G');
        set_time_limit(0);

        $archivo = $this->argument('archivo');
        if (! is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");

            return self::FAILURE;
        }
        $corte = $this->option('corte');
        $letras = array_filter(array_map('trim', explode(',', strtoupper($this->option('hojas')))));

        $propietarios = [];
        if ($this->option('propietarios')) {
            $propietarios = $this->leerPropietarios($this->option('propietarios'));
        } else {
            $this->warn('Sin --propietarios: se usará el nombre que aparece en el título de cada villa.');
        }

        $this->info('Leyendo el Excel (puede tardar un par de minutos)...');
        $plan = $importador->planificar($lector->leer($archivo, $letras), $propietarios, $corte);

        $this->reporte($plan);

        if ($detalle = $this->option('detalle')) {
            $this->detalleVilla($plan, strtoupper($detalle));
        }
        if ($ruta = $this->option('sql')) {
            file_put_contents($ruta, $importador->sql($plan));
            $this->info("Script SQL para Railway escrito en: {$ruta}");
        }
        if ($this->option('aplicar')) {
            $importador->aplicar($plan);
            $this->info('Datos cargados en la base actual ('.config('database.default').').');
        }

        return self::SUCCESS;
    }

    /** @return array<string, list<array{propietario: string, desde: ?string}>> */
    private function leerPropietarios(string $ruta): array
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($ruta)); // BOM de Excel
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252'); // CSV guardado por Excel en Windows
        }
        $lineas = array_values(array_filter(preg_split('/\r\n|\n|\r/', $contenido), fn ($l) => trim($l, " ;,\t") !== ''));
        $sep = substr_count($lineas[0] ?? '', ';') >= substr_count($lineas[0] ?? '', ',') ? ';' : ',';

        $resultado = [];
        foreach (array_slice($lineas, 1) as $n => $linea) {
            [$villa, $nombre, $desde] = array_map('trim', str_getcsv($linea, $sep)) + [null, null, null];
            if (! $villa || ! $nombre) {
                continue;
            }
            $villa = strtoupper(preg_replace('/^([A-Z])\s*-?\s*(\d+)$/i', '$1-$2', $villa));
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
            $filas[] = [
                $v['codigo'],
                mb_substr($plan['propietarios'][$v['propietario']]['NOMBRES'].' '.$plan['propietarios'][$v['propietario']]['APELLIDOS'], 0, 34),
                count($v['anteriores']) ?: '',
                number_format($v['saldo_corte'], 2),
                count($v['movimientos']),
                $v['ajustes'] ?: '',
                number_format($v['saldo_final'], 2),
                number_format($v['saldo_excel'], 2),
                abs($v['saldo_final'] - $v['saldo_excel']) < 0.01 ? 'OK' : 'REVISAR',
            ];
        }
        $this->table(['Villa', 'Propietario actual', 'Anteriores', 'Saldo al corte', 'Movs', 'Ajustes', 'Saldo final', 'Saldo Excel', ''], $filas);

        $movs = array_sum(array_map(fn ($v) => count($v['movimientos']), $plan['villas']));
        $ajustes = array_sum(array_column($plan['villas'], 'ajustes'));
        $this->line(sprintf('%d villas, %d propietarios, %d movimientos (%d ajustes). Corte: %s.',
            count($plan['villas']), count($plan['propietarios']), $movs, $ajustes, $plan['corte']));

        $avisos = $plan['avisos'];
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
        $saldo = 0;
        $this->table(['Fecha', 'Concepto', 'Forma', 'Cargo', 'Crédito', 'Saldo', 'Observación'], array_map(function ($m) use (&$saldo) {
            $esCargo = in_array($m['concepto'], [ImportadorEstadosCuenta::SALDO_INICIAL, ImportadorEstadosCuenta::AJUSTE_CARGO, 'Cargo extraordinario', 'Mora', '__mantenimiento__'], true);
            $saldo += $esCargo ? $m['importe'] : -$m['importe'];

            return [$m['fecha'], $m['concepto'] === '__mantenimiento__' ? 'Cuota de mantenimiento' : $m['concepto'], $m['forma'] ?? '',
                $esCargo ? number_format($m['importe'], 2) : '', $esCargo ? '' : number_format($m['importe'], 2), number_format($saldo, 2), mb_substr($m['obs'], 0, 60)];
        }, $v['movimientos']));
    }
}
