<?php

namespace App\Services\Importacion;

use App\Models\Bitacora;
use App\Models\Concepto;
use App\Models\FormaPago;
use App\Models\Movimiento;
use App\Models\Propietario;
use App\Models\Villa;
use App\Models\VillaHistorial;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Convierte los bloques leidos del Excel (LectorEstadosCuenta) en un "plan" de importacion y lo
 * aplica a la base actual (aplicar) o lo escribe como script SQL para PostgreSQL (sql). Ambos usan el
 * mismo plan, asi lo que se revisa en local es exactamente lo que se carga en Railway.
 *
 * Reglas (acordadas con el usuario):
 * - Cada villa arranca con su saldo del Excel a la fecha de corte como "Saldo inicial" y se cargan
 *   en detalle los movimientos posteriores.
 * - Manda el saldo del Excel (columna F): si despues de un movimiento la suma no coincide con el
 *   saldo del Excel, se agrega un "Ajuste de saldo" por la diferencia.
 * - Los propietarios salen de un CSV revisado por el usuario (villa;propietario;desde): el ultimo
 *   de cada villa es el actual y los anteriores van al historial de la villa.
 */
class ImportadorEstadosCuenta
{
    public const SALDO_INICIAL = 'Saldo inicial';

    public const SALDO_INICIAL_FAVOR = 'Saldo inicial a favor';

    public const AJUSTE_CARGO = 'Ajuste de saldo (cargo)';

    public const AJUSTE_CREDITO = 'Ajuste de saldo (crédito)';

    /** Conceptos que crea la importacion si no existen (inactivos: no se ofrecen en Cargo/Crédito). */
    public const CONCEPTOS_IMPORTACION = [
        self::SALDO_INICIAL => true,
        self::SALDO_INICIAL_FAVOR => false,
        self::AJUSTE_CARGO => true,
        self::AJUSTE_CREDITO => false,
    ];

    private const MANTENIMIENTO = '__mantenimiento__';

    /** Numeros de documento de la columna A (se guardan en la observacion del movimiento). */
    private const REGEX_DOCUMENTO = '/^(fac\w*|fc|rec\w*|recibo|re|en\s*cta|em\s*cta|pgo|pag|nc|nd|ck|chq|trans|s\/r|cxc)\b|^\d/i';

    /**
     * @param  list<array>  $bloques  de LectorEstadosCuenta::leer()
     * @param  array<string, list<array{propietario: string, desde: ?string}>>  $propietarios  por villa
     */
    public function planificar(array $bloques, array $propietarios, string $corte, ?Carbon $hoy = null): array
    {
        $hoy ??= Carbon::today();
        $avisos = [];

        // Una villa puede tener varios bloques (ej. el de un propietario anterior): se usa el mas reciente.
        $porVilla = [];
        foreach ($bloques as $b) {
            if (! $b['codigo']) {
                $avisos[] = "{$b['hoja']} fila {$b['fila']}: bloque \"{$b['titulo']}\" sin número de villa, se ignora.";

                continue;
            }
            $ultima = collect($b['filas'])->pluck('fecha')->filter()->max();
            if (isset($porVilla[$b['codigo']])) {
                $otro = $porVilla[$b['codigo']];
                $ultimaOtro = collect($otro['filas'])->pluck('fecha')->filter()->max();
                [$usado, $ignorado] = $ultima > $ultimaOtro ? [$b, $otro] : [$otro, $b];
                $avisos[] = "{$b['codigo']}: hay dos bloques (\"{$usado['titulo']}\" fila {$usado['fila']} y \"{$ignorado['titulo']}\" fila {$ignorado['fila']}); se usa el más reciente (fila {$usado['fila']}).";
                $porVilla[$b['codigo']] = $usado;
            } else {
                $porVilla[$b['codigo']] = $b;
            }
        }
        uksort($porVilla, fn ($x, $y) => strnatcmp($x, $y));

        $villas = [];
        $catalogoPropietarios = [];
        foreach ($porVilla as $codigo => $bloque) {
            $villa = $this->planVilla($codigo, $bloque, $corte, $hoy);

            // propietarios: del CSV revisado; si la villa no esta, el nombre del titulo del bloque
            $lista = $propietarios[$codigo] ?? [];
            if (! $lista) {
                $lista = [['propietario' => $this->nombreDesdeTitulo($bloque['titulo']), 'desde' => null]];
                $villa['avisos'][] = 'No está en la lista de propietarios: se usa el nombre del Excel ("'.$lista[0]['propietario'].'").';
            }
            usort($lista, fn ($x, $y) => strcmp((string) $x['desde'], (string) $y['desde']));

            // "Victor Gonzales / Luisa Reyes" o "Magaly Martinez y Jorge Max": la villa esta a nombre de
            // varias personas. El propietario registrado es el primero y en la observacion de la villa se
            // anota a nombre de quienes esta.
            $nombres = fn (string $texto) => array_values(array_filter(array_map('trim', preg_split('/\s*\/\s*|\s+y\s+/iu', $texto))));
            $titulares = $nombres(end($lista)['propietario']);
            $villa['observacion'] = count($titulares) > 1
                ? 'VILLA A NOMBRE DE: '.mb_strtoupper(implode(' / ', $titulares), 'UTF-8')
                : null;
            $personas = array_map(fn ($p) => $this->persona($nombres($p['propietario'])[0]) + ['desde' => $p['desde'] ?: null], $lista);

            $actual = array_pop($personas);
            $catalogoPropietarios[$actual['clave']] ??= $actual;
            $villa['propietario'] = $actual['clave'];
            $villa['propietario_desde'] = $actual['desde'];
            $villa['anteriores'] = [];
            foreach ($personas as $i => $p) {
                $siguiente = $personas[$i + 1] ?? $actual;
                if (! $siguiente['desde']) {
                    // no se sabe cuando cambio: se deja la fecha de corte (el ultimo dato "viejo" del Excel)
                    $villa['avisos'][] = "No se sabe la fecha en que {$p['clave']} dejó de ser propietario: en el historial queda hasta el ".Carbon::parse($corte)->format('d/m/Y').'.';
                }
                $villa['anteriores'][] = $p + ['hasta' => $siguiente['desde'] ?? $corte];
            }

            $villas[$codigo] = $villa;
        }

        foreach (array_diff(array_keys($propietarios), array_keys($villas)) as $sinBloque) {
            $avisos[] = "{$sinBloque}: está en la lista de propietarios pero no se encontró en el Excel.";
        }

        return [
            'corte' => $corte,
            'villas' => $villas,
            'propietarios' => $catalogoPropietarios,
            'avisos' => $avisos,
        ];
    }

    private function planVilla(string $codigo, array $bloque, string $corte, Carbon $hoy): array
    {
        $avisos = $bloque['notas'] ?? [];
        if ($bloque['codigo_inferido']) {
            $avisos[] = "El título del bloque (\"{$bloque['titulo']}\", fila {$bloque['fila']}) no trae número de villa: se asumió {$codigo} por ir después de la anterior.";
        }

        // 1) saldo a la fecha de corte: el del Excel en la ultima fila antes del primer movimiento posterior
        $saldo = 0.0;
        $i = 0;
        $filas = $bloque['filas'];
        for (; $i < count($filas); $i++) {
            $f = $filas[$i];
            if ($f['fecha'] !== null && $f['fecha'] > $corte) {
                break;
            }
            if ($f['fecha'] === null) {
                continue;
            }
            $saldo = round($saldo + ($f['debe'] ?? 0) - ($f['haber'] ?? 0), 2);
            if ($f['saldo'] !== null) {
                $saldo = $f['saldo'];
            }
        }

        $movs = [];
        if (abs($saldo) >= 0.01) {
            $movs[] = [
                'fecha' => $corte,
                'concepto' => $saldo > 0 ? self::SALDO_INICIAL : self::SALDO_INICIAL_FAVOR,
                'forma' => null,
                'importe' => abs($saldo),
                'obs' => "Saldo al ".Carbon::parse($corte)->format('d/m/Y')." según Excel de estados de cuenta",
            ];
        }

        // 2) movimientos posteriores, ajustando al saldo del Excel cuando no coincide
        $running = $saldo;
        $ajustes = 0;
        $sinFecha = 0;
        $ultimaFecha = $corte;
        $saldoExcel = $saldo;
        for (; $i < count($filas); $i++) {
            $f = $filas[$i];
            $hayMonto = ($f['debe'] ?? 0) != 0 || ($f['haber'] ?? 0) != 0;
            if ($f['fecha'] === null) {
                if ($hayMonto) {
                    $sinFecha++;
                }

                continue;
            }
            $ultimaFecha = max($ultimaFecha, $f['fecha']);
            $obs = $this->observacion($f);

            foreach ([['debe', true], ['haber', false]] as [$col, $esCargo]) {
                $monto = $f[$col] ?? 0;
                if ($monto == 0) {
                    continue;
                }
                // un monto negativo en "debe" es en realidad un credito (y viceversa)
                $cargo = $monto > 0 ? $esCargo : ! $esCargo;
                $importe = abs($monto);
                $movs[] = [
                    'fecha' => $f['fecha'],
                    'concepto' => $cargo ? $this->conceptoCargo($f['descripcion']) : 'Abono / Pago',
                    'forma' => $cargo ? null : $this->formaPago($f['descripcion']),
                    'importe' => $importe,
                    'obs' => $obs,
                ];
                $running = round($running + ($cargo ? $importe : -$importe), 2);
            }

            if ($f['saldo'] !== null) {
                $saldoExcel = $f['saldo'];
                $diferencia = round($f['saldo'] - $running, 2);
                if (abs($diferencia) >= 0.01) {
                    $movs[] = [
                        'fecha' => $f['fecha'],
                        'concepto' => $diferencia > 0 ? self::AJUSTE_CARGO : self::AJUSTE_CREDITO,
                        'forma' => null,
                        'importe' => abs($diferencia),
                        'obs' => 'Ajuste para igualar el saldo del Excel (fila '.$f['fila'].')',
                    ];
                    $running = $f['saldo'];
                    $ajustes++;
                }
            }
        }

        if ($sinFecha) {
            $avisos[] = "{$sinFecha} fila(s) con monto pero sin fecha después del corte: no se cargan (el saldo final igual queda como el del Excel gracias a los ajustes).";
        }
        $futuros = count(array_filter($movs, fn ($m) => $m['fecha'] > $hoy->toDateString()));
        if ($futuros) {
            $avisos[] = "{$futuros} movimiento(s) con fecha futura: el sistema los cuenta en el saldo cuando llegue su fecha.";
        }

        return [
            'codigo' => $codigo,
            'dir' => 'BLOQUE '.explode('-', $codigo)[0],
            'titulo' => $bloque['titulo'],
            'hoja' => $bloque['hoja'],
            'fila' => $bloque['fila'],
            'saldo_corte' => $saldo,
            'saldo_final' => $running,
            'saldo_excel' => $saldoExcel,
            'ultima_fecha' => $ultimaFecha,
            'ajustes' => $ajustes,
            'movimientos' => $movs,
            'avisos' => $avisos,
        ];
    }

    private function conceptoCargo(?string $descripcion): string
    {
        $d = mb_strtolower((string) $descripcion);
        if (preg_match('/\bmora\b|recargo|inter[eé]s/u', $d)) {
            return 'Mora';
        }
        if (preg_match('/mant[ei]n|cuota/u', $d)) {
            return self::MANTENIMIENTO;
        }

        return 'Cargo extraordinario';
    }

    private function formaPago(?string $descripcion): ?string
    {
        $d = mb_strtolower((string) $descripcion);

        return match (true) {
            (bool) preg_match('/transf|\btrans\b/u', $d) => 'Transferencia',
            (bool) preg_match('/dep[oó]sito|\bdep\b/u', $d) => 'Depósito',
            (bool) preg_match('/cheque|\bck\b|\bchq\b/u', $d) => 'Cheque',
            (bool) preg_match('/efectivo/u', $d) => 'Efectivo',
            default => null,
        };
    }

    private function observacion(array $fila): string
    {
        $doc = $fila['a'] !== null && preg_match(self::REGEX_DOCUMENTO, $fila['a']) ? $fila['a'] : null;
        $texto = trim(implode(' · ', array_filter([$doc, $fila['descripcion']])));

        return mb_substr($texto !== '' ? $texto : 'Importado del Excel (fila '.$fila['fila'].')', 0, 255);
    }

    /** "Marieta Yolanda Miranda A1" -> "MARIETA YOLANDA MIRANDA" */
    private function nombreDesdeTitulo(string $titulo): string
    {
        $t = preg_replace('/(?<![A-Za-z])[A-D]\s*-?\s*\d{1,2}(?!\d)/i', ' ', $titulo);
        $t = preg_replace('/\bvilla\b/i', ' ', $t);

        return trim(preg_replace('/\s+/', ' ', trim($t, " /-")));
    }

    /**
     * Nombre completo -> NOMBRES / APELLIDOS (el sistema los guarda separados). Con 4+ palabras, las
     * dos primeras son nombres; con 3, la primera; con 2, una y una. Empresas o una sola palabra
     * quedan completas en NOMBRES. Se puede corregir despues desde la ficha del propietario.
     */
    private function persona(string $nombreCompleto): array
    {
        $limpio = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $nombreCompleto)), 'UTF-8');
        $palabras = explode(' ', $limpio);
        // empresas o varias personas ("X Y Z", "A / B"): todo el texto queda en NOMBRES
        $esEmpresa = (bool) preg_match('/\b(S\.?\s?A|S DE RL|GRUPO|INVERSIONES|CORREDURIA|PROMOTUR|INTERAMERICANA|FICOHSA|CONSTANCIA)\b|\/|\sY\s|\(/u', $limpio);
        $n = count($palabras);
        [$nombres, $apellidos] = match (true) {
            $esEmpresa || $n === 1 => [$limpio, ''],
            $n === 2 => [$palabras[0], $palabras[1]],
            $n === 3 => [$palabras[0], $palabras[1].' '.$palabras[2]],
            default => [implode(' ', array_slice($palabras, 0, 2)), implode(' ', array_slice($palabras, 2))],
        };

        return ['clave' => $limpio, 'NOMBRES' => mb_substr($nombres, 0, 60), 'APELLIDOS' => mb_substr($apellidos, 0, 60)];
    }

    // ------------------------------------------------------------------------------ aplicar (BD actual)

    public function aplicar(array $plan): void
    {
        DB::transaction(function () use ($plan) {
            $existentes = Villa::whereIn('CLV_CLIE', array_keys($plan['villas']))->pluck('CLV_CLIE');
            if ($existentes->isNotEmpty()) {
                throw new \RuntimeException('Estas villas ya existen en la base y no se sobrescriben: '.$existentes->join(', '));
            }

            $conceptos = $this->asegurarConceptos();
            $formas = FormaPago::pluck('id', 'nombre');
            $ahora = now();

            $idPropietario = [];
            foreach ($plan['propietarios'] as $clave => $p) {
                $idPropietario[$clave] = Propietario::create(['NOMBRES' => $p['NOMBRES'], 'APELLIDOS' => $p['APELLIDOS']])->id;
            }

            foreach ($plan['villas'] as $v) {
                Villa::create([
                    'CLV_CLIE' => $v['codigo'], 'PROPIETARIO_ID' => $idPropietario[$v['propietario']], 'DIR' => $v['dir'], 'OBSERVACION' => $v['observacion'],
                    'APLICOBRO' => true, 'CUOTA_ESPECIAL' => false, 'SALDO' => $v['saldo_final'],
                ]);
                foreach ($v['anteriores'] as $a) {
                    VillaHistorial::create([
                        'CLV_CLIE' => $v['codigo'], 'TIPO' => VillaHistorial::PROPIETARIO,
                        'NOMBRES' => $a['NOMBRES'], 'APELLIDOS' => $a['APELLIDOS'], 'DESDE' => $a['desde'], 'HASTA' => $a['hasta'],
                    ]);
                }
                $filas = array_map(fn ($m) => [
                    'CLV_CLIE' => $v['codigo'], 'NUM_CPTO' => $conceptos[$m['concepto']], 'FORMA_PAGO_ID' => $m['forma'] ? ($formas[$m['forma']] ?? null) : null,
                    'IMPORTE' => $m['importe'], 'FECHA_APLI' => $m['fecha'], 'ANIO' => (int) substr($m['fecha'], 0, 4), 'MES' => (int) substr($m['fecha'], 5, 2),
                    'OBS' => $m['obs'], 'created_at' => $ahora, 'updated_at' => $ahora,
                ], $v['movimientos']);
                foreach (array_chunk($filas, 200) as $lote) {
                    Movimiento::insert($lote);
                }
            }

            Bitacora::registrar('movimiento', 'crear', $this->textoBitacora($plan));
        });
    }

    /** @return array<string, int> DESCR (o la marca de mantenimiento) -> NUM_CPTO */
    private function asegurarConceptos(): array
    {
        foreach (self::CONCEPTOS_IMPORTACION + ['Cargo extraordinario' => true, 'Mora' => true, 'Abono / Pago' => false] as $descr => $esCargo) {
            Concepto::firstOrCreate(['DESCR' => $descr], [
                'ES_CARGO' => $esCargo,
                'ACTIVO' => ! array_key_exists($descr, self::CONCEPTOS_IMPORTACION),
            ]);
        }
        $mantenimiento = Concepto::mantenimiento() ?? throw new \RuntimeException('No existe el concepto de cuota de mantenimiento.');

        return Concepto::pluck('NUM_CPTO', 'DESCR')->all() + [self::MANTENIMIENTO => $mantenimiento->NUM_CPTO];
    }

    private function textoBitacora(array $plan): string
    {
        $movs = array_sum(array_map(fn ($v) => count($v['movimientos']), $plan['villas']));

        return mb_substr('Importó del Excel de estados de cuenta '.count($plan['villas']).' villas, '
            .count($plan['propietarios']).' propietarios y '.$movs.' movimientos (saldo inicial al '
            .Carbon::parse($plan['corte'])->format('d/m/Y').').', 0, 255);
    }

    // ------------------------------------------------------------------------------ script SQL (Railway)

    /** Script para PostgreSQL (DBeaver): todo en una transaccion; si una villa ya existe, no carga nada. */
    public function sql(array $plan): string
    {
        $q = fn (?string $s) => $s === null ? 'NULL' : "'".str_replace("'", "''", $s)."'";
        $n = fn ($x) => $x === null ? 'NULL' : number_format((float) $x, 2, '.', '');
        $codigos = implode(', ', array_map($q, array_keys($plan['villas'])));

        $s = [];
        $s[] = '-- Importacion de estados de cuenta desde Excel. Generado '.now()->format('d/m/Y H:i').'.';
        $s[] = '-- Ejecutar completo en DBeaver con Alt+X. Si algo falla, no se carga nada (ROLLBACK).';
        $s[] = 'BEGIN;';
        $s[] = 'SET LOCAL search_path TO public;';
        $s[] = "DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM villas WHERE \"CLV_CLIE\" IN ({$codigos})) THEN RAISE EXCEPTION 'Algunas villas ya existen; no se importa nada.'; END IF; END \$\$;";
        $s[] = "DO \$\$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'villas' AND column_name = 'OBSERVACION') THEN RAISE EXCEPTION 'Railway todavía no tiene la última versión del sistema (falta la columna OBSERVACION de villas). Espera a que termine el despliegue y vuelve a correr el script.'; END IF; END \$\$;";
        $s[] = "DO \$\$ BEGIN IF NOT EXISTS (SELECT 1 FROM conceptos WHERE \"ES_MANTENIMIENTO\" = true) THEN RAISE EXCEPTION 'No existe el concepto de cuota de mantenimiento.'; END IF; END \$\$;";
        $s[] = '';
        $s[] = '-- conceptos que usa la importacion (inactivos si son propios de la importacion)';
        foreach (self::CONCEPTOS_IMPORTACION + ['Cargo extraordinario' => true, 'Mora' => true, 'Abono / Pago' => false] as $descr => $esCargo) {
            $activo = array_key_exists($descr, self::CONCEPTOS_IMPORTACION) ? 'false' : 'true';
            $s[] = "INSERT INTO conceptos (\"DESCR\", \"ES_CARGO\", \"ACTIVO\", \"ES_MANTENIMIENTO\") SELECT {$q($descr)}, ".($esCargo ? 'true' : 'false').", {$activo}, false WHERE NOT EXISTS (SELECT 1 FROM conceptos WHERE \"DESCR\" = {$q($descr)});";
        }
        $s[] = '';
        $s[] = 'CREATE TEMP TABLE imp_prop (clave text PRIMARY KEY, id bigint) ON COMMIT DROP;';
        foreach ($plan['propietarios'] as $clave => $p) {
            $s[] = "WITH ins AS (INSERT INTO propietarios (\"NOMBRES\", \"APELLIDOS\", created_at, updated_at) VALUES ({$q($p['NOMBRES'])}, {$q($p['APELLIDOS'])}, now(), now()) RETURNING id) INSERT INTO imp_prop SELECT {$q($clave)}, id FROM ins;";
        }

        $concepto = fn (string $c) => $c === self::MANTENIMIENTO
            ? '(SELECT "NUM_CPTO" FROM conceptos WHERE "ES_MANTENIMIENTO" = true ORDER BY "NUM_CPTO" LIMIT 1)'
            : "(SELECT \"NUM_CPTO\" FROM conceptos WHERE \"DESCR\" = {$q($c)} ORDER BY \"NUM_CPTO\" LIMIT 1)";

        foreach ($plan['villas'] as $v) {
            $s[] = '';
            $s[] = "-- {$v['codigo']} ({$v['titulo']})";
            $s[] = "INSERT INTO villas (\"CLV_CLIE\", \"PROPIETARIO_ID\", \"DIR\", \"OBSERVACION\", \"APLICOBRO\", \"CUOTA_ESPECIAL\", \"SALDO\", created_at, updated_at) VALUES ({$q($v['codigo'])}, (SELECT id FROM imp_prop WHERE clave = {$q($v['propietario'])}), {$q($v['dir'])}, {$q($v['observacion'])}, true, false, {$n($v['saldo_final'])}, now(), now());";
            foreach ($v['anteriores'] as $a) {
                $s[] = "INSERT INTO villa_historial (\"CLV_CLIE\", \"TIPO\", \"NOMBRES\", \"APELLIDOS\", \"DESDE\", \"HASTA\", created_at, updated_at) VALUES ({$q($v['codigo'])}, 'propietario', {$q($a['NOMBRES'])}, {$q($a['APELLIDOS'])}, {$q($a['desde'])}, {$q($a['hasta'])}, now(), now());";
            }
            foreach (array_chunk($v['movimientos'], 100) as $lote) {
                $valores = array_map(fn ($m) => "({$q($v['codigo'])}, {$concepto($m['concepto'])}, "
                    .($m['forma'] ? "(SELECT id FROM formas_pago WHERE nombre = {$q($m['forma'])})" : 'NULL')
                    .", {$n($m['importe'])}, {$q($m['fecha'])}, ".(int) substr($m['fecha'], 0, 4).', '.(int) substr($m['fecha'], 5, 2)
                    .", {$q($m['obs'])}, now(), now())", $lote);
                $s[] = 'INSERT INTO movimientos ("CLV_CLIE", "NUM_CPTO", "FORMA_PAGO_ID", "IMPORTE", "FECHA_APLI", "ANIO", "MES", "OBS", created_at, updated_at) VALUES'
                    ."\n  ".implode(",\n  ", $valores).';';
            }
        }

        $s[] = '';
        $s[] = "INSERT INTO bitacora (usuario_id, entidad, accion, descripcion, created_at) VALUES (NULL, 'movimiento', 'crear', {$q($this->textoBitacora($plan))}, now());";
        $s[] = 'COMMIT;';
        $s[] = '';
        $s[] = '-- Verificacion: saldo de cada villa importada (debe coincidir con el reporte del importador)';
        $s[] = "SELECT m.\"CLV_CLIE\", SUM(CASE WHEN c.\"ES_CARGO\" THEN m.\"IMPORTE\" ELSE -m.\"IMPORTE\" END) AS saldo, COUNT(*) AS movimientos FROM movimientos m JOIN conceptos c ON c.\"NUM_CPTO\" = m.\"NUM_CPTO\" WHERE m.\"CLV_CLIE\" IN ({$codigos}) GROUP BY m.\"CLV_CLIE\" ORDER BY m.\"CLV_CLIE\";";

        return implode("\n", $s)."\n";
    }
}
