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

    public const MANTENIMIENTO = '__mantenimiento__';

    /**
     * Cargos de energia electrica: van al concepto "Energía Eléctrica" (creado por el usuario). Se busca
     * sin distinguir mayusculas ni tildes; si no existe, la importacion lo crea con este nombre.
     */
    public const ENERGIA = '__energia__';

    private const NOMBRE_ENERGIA = 'Energía Eléctrica';

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

            $villas[$codigo] = $this->asignarPropietarios($villa, $lista, $corte, $catalogoPropietarios);
        }

        foreach (array_diff(array_keys($propietarios), array_keys($villas)) as $sinBloque) {
            $avisos[] = "{$sinBloque}: está en la lista de propietarios pero no se encontró en el Excel.";
        }

        return [
            'corte' => $corte,
            'villas' => $villas,
            'propietarios' => $this->nombresDelCatalogo($catalogoPropietarios),
            'avisos' => $avisos,
        ];
    }

    /**
     * Propietario actual (el ultimo de la lista) e historial (los anteriores, en orden) de una villa.
     * Si la villa esta a nombre de varias personas ("Victor Gonzales / Luisa Reyes"), el propietario
     * registrado es el primero y la observacion de la villa dice a nombre de quienes esta (ver
     * NombrePropietario). La misma persona en varias villas es un solo propietario aunque su nombre
     * venga escrito distinto.
     *
     * @param  list<array{propietario: string, desde: ?string}>  $lista  del mas antiguo al actual
     * @param  string  $hastaDesconocido  fecha "hasta" del historial cuando no se sabe cuando cambio
     */
    public function asignarPropietarios(array $villa, array $lista, string $hastaDesconocido, array &$catalogo): array
    {
        $personas = array_map(fn ($p) => NombrePropietario::propietario($p['propietario']) + ['desde' => $p['desde'] ?: null], $lista);

        $actual = array_pop($personas);
        $catalogo[$actual['clave']][$actual['NOMBRES'].'|'.$actual['APELLIDOS']][] = $villa['codigo'];
        $villa['propietario'] = $actual['clave'];
        $villa['propietario_desde'] = $actual['desde'];
        $villa['observacion'] = $actual['observacion'];
        $villa['anteriores'] = [];
        foreach ($personas as $i => $p) {
            $siguiente = $personas[$i + 1] ?? $actual;
            if (! $siguiente['desde']) {
                $villa['avisos'][] = "No se sabe la fecha en que {$p['NOMBRES']} {$p['APELLIDOS']} dejó de ser propietario: en el historial queda hasta el ".Carbon::parse($hastaDesconocido)->format('d/m/Y').'.';
            }
            $villa['anteriores'][] = ['NOMBRES' => $p['NOMBRES'], 'APELLIDOS' => $p['APELLIDOS'], 'desde' => $p['desde'], 'hasta' => $siguiente['desde'] ?? $hastaDesconocido];
        }

        return $villa;
    }

    /**
     * Catalogo de asignarPropietarios() -> clave => NOMBRES/APELLIDOS. Si la misma persona viene escrita
     * de varias formas, se usa la que mas se repite (a igualdad, la primera).
     *
     * @return array<string, array{NOMBRES: string, APELLIDOS: string, variantes: array<string, list<string>>}>
     */
    public function nombresDelCatalogo(array $catalogo): array
    {
        $resultado = [];
        foreach ($catalogo as $clave => $variantes) {
            $mejor = array_key_first($variantes);
            foreach ($variantes as $v => $villas) {
                if (count($villas) > count($variantes[$mejor])) {
                    $mejor = $v;
                }
            }
            [$nombres, $apellidos] = explode('|', $mejor);
            $resultado[$clave] = ['NOMBRES' => $nombres, 'APELLIDOS' => $apellidos, 'variantes' => $variantes];
        }

        return $resultado;
    }

    private function planVilla(string $codigo, array $bloque, string $corte, Carbon $hoy): array
    {
        $avisos = $bloque['notas'] ?? [];
        if ($bloque['codigo_inferido']) {
            $avisos[] = "El título del bloque (\"{$bloque['titulo']}\", fila {$bloque['fila']}) no trae número de villa: se asumió {$codigo} por ir después de la anterior.";
        }
        $bloque['filas'] = $this->corregirAniosMalEscritos($bloque['filas'], $avisos);

        // 1) Punto de corte: el Excel tiene fechas fuera de orden (y alguna mal escrita), asi que no basta
        //    con "la primera fila posterior al corte". Se elige el punto (en el orden del Excel) que mejor
        //    separa "antes" de "despues": el que deja menos filas fuera de lugar. Ej. A-12 tiene un
        //    "24/12/2026" entre filas de dic-2022 y ene-2023: queda antes del corte e incluido en el saldo,
        //    como en el Excel, en vez de partir ahi el corte y cargarse como pago a futuro.
        $filas = $bloque['filas'];
        $split = $this->mejorPuntoDeCorte($filas, $corte);
        foreach ($filas as $k => $f) {
            if ($f['fecha'] === null) {
                continue;
            }
            if ($k < $split && $f['fecha'] > $corte) {
                $avisos[] = "Fila {$f['fila']}: fecha {$f['fecha']} entre movimientos anteriores al corte (¿fecha mal escrita?); queda incluida en el saldo al corte, como en el Excel.";
            } elseif ($k >= $split && $f['fecha'] <= $corte && ($f['debe'] || $f['haber'])) {
                $avisos[] = "Fila {$f['fila']}: fecha {$f['fecha']} entre movimientos posteriores al corte; se carga con su fecha.";
            }
        }

        // saldo al corte: el del Excel en la ultima fila antes del punto de corte
        $saldo = 0.0;
        $i = 0;
        for (; $i < $split; $i++) {
            $f = $filas[$i];
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

    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /**
     * Corrige años mal escritos a mano. El saldo del Excel (columna F) es el correcto y sigue el orden de
     * las filas; una fila cuya fecha no encaja entre la anterior y la siguiente, pero SI encaja con el
     * mismo dia y mes en el año de una de ellas, tiene el año mal escrito (ej. A-9 fila 2285: "28/01/2026"
     * entre el 02/01/2025 y el 01/02/2025 -> 28/01/2025). Si la descripcion trae ese mismo mes con el año
     * equivocado (ej. "Cuota de mantenimiento de Marzo 2025" en una fila de marzo 2026), tambien se corrige.
     * El saldo no cambia: solo la fecha en que queda el movimiento.
     */
    public function corregirAniosMalEscritos(array $filas, array &$avisos = []): array
    {
        $conMonto = array_keys(array_filter($filas, fn ($f) => $f['fecha'] !== null && ($f['debe'] || $f['haber'])));
        $correcciones = [];
        for ($j = 1; $j < count($conMonto) - 1; $j++) {
            [$prev, $f, $next] = [$filas[$conMonto[$j - 1]], $filas[$conMonto[$j]], $filas[$conMonto[$j + 1]]];
            $fueraDeOrden = $prev['fecha'] <= $next['fecha'] && ($f['fecha'] < $prev['fecha'] || $f['fecha'] > $next['fecha']);
            if (! $fueraDeOrden) {
                continue;
            }
            foreach ([substr($prev['fecha'], 0, 4), substr($next['fecha'], 0, 4)] as $anio) {
                $candidata = $anio.substr($f['fecha'], 4);
                if ($anio !== substr($f['fecha'], 0, 4) && $candidata >= $prev['fecha'] && $candidata <= $next['fecha'] && checkdate((int) substr($candidata, 5, 2), (int) substr($candidata, 8, 2), (int) $anio)) {
                    $correcciones[$conMonto[$j]] = $candidata;
                    break;
                }
            }
        }

        foreach ($correcciones as $k => $nueva) {
            $antes = $filas[$k]['fecha'];
            $filas[$k]['fecha'] = $nueva;
            $aviso = "Fila {$filas[$k]['fila']}: fecha con el año mal escrito, se corrigió ".Carbon::parse($antes)->format('d/m/Y').' → '.Carbon::parse($nueva)->format('d/m/Y');

            // descripcion con el mismo mes y el año equivocado ("... Marzo 2025" en una fila de marzo 2026)
            $mes = self::MESES[(int) substr($nueva, 5, 2) - 1];
            $anioNuevo = substr($nueva, 0, 4);
            $descripcion = (string) $filas[$k]['descripcion'];
            $corregida = preg_replace_callback('/\b('.$mes.')\s+(\d{4})\b/iu', fn ($m) => $m[2] === $anioNuevo ? $m[0] : $m[1].' '.$anioNuevo, $descripcion);
            if ($corregida !== $descripcion) {
                $filas[$k]['descripcion'] = $corregida;
                $aviso .= " y en la descripción (\"{$descripcion}\" → \"{$corregida}\")";
            }
            $avisos[] = $aviso.'.';
        }

        return $filas;
    }

    /**
     * Indice (en el orden del Excel) donde empiezan los movimientos posteriores al corte: el que minimiza
     * las filas fuera de lugar (con fecha posterior al corte antes del punto + con fecha anterior despues).
     * Ante empate, el mas tardio (asi una fecha mal escrita queda dentro del saldo del Excel).
     */
    private function mejorPuntoDeCorte(array $filas, string $corte): int
    {
        $n = count($filas);
        $despuesAnteriores = 0; // filas con fecha <= corte desde el punto en adelante
        foreach ($filas as $f) {
            if ($f['fecha'] !== null && $f['fecha'] <= $corte) {
                $despuesAnteriores++;
            }
        }

        $mejor = 0;
        $mejorCosto = PHP_INT_MAX;
        $antesPosteriores = 0; // filas con fecha > corte antes del punto
        for ($p = 0; $p <= $n; $p++) {
            $costo = $antesPosteriores + $despuesAnteriores;
            if ($costo <= $mejorCosto) {
                $mejorCosto = $costo;
                $mejor = $p;
            }
            if ($p < $n && $filas[$p]['fecha'] !== null) {
                $filas[$p]['fecha'] > $corte ? $antesPosteriores++ : $despuesAnteriores--;
            }
        }

        // no pasar del ultimo movimiento con fecha (las filas finales sin fecha no cambian el saldo)
        while ($mejor > 0 && $filas[$mejor - 1]['fecha'] === null) {
            $mejor--;
        }

        return $mejor;
    }

    private function conceptoCargo(?string $descripcion): string
    {
        $d = mb_strtolower((string) $descripcion);
        if (preg_match('/\bmora\b|recargo|inter[eé]s/u', $d)) {
            return 'Mora';
        }
        if (preg_match('/energ|el[eé]ctric|\benee\b/u', $d)) {
            return self::ENERGIA;
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

    // ------------------------------------------------------------------------------ aplicar (BD actual)

    /** @param  bool  $reemplazar  borra antes todas las villas, propietarios, historial y movimientos (como el script SQL) */
    public function aplicar(array $plan, bool $reemplazar = false): void
    {
        DB::transaction(function () use ($plan, $reemplazar) {
            if ($reemplazar) {
                $this->vaciar();
            }
            $existentes = Villa::whereIn('CLV_CLIE', array_keys($plan['villas']))->pluck('CLV_CLIE');
            if ($existentes->isNotEmpty()) {
                throw new \RuntimeException('Estas villas ya existen en la base y no se sobrescriben: '.$existentes->join(', '));
            }

            $conceptos = $this->asegurarConceptos($plan);
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

    /** Lo mismo que hace el TRUNCATE del script SQL, en la base actual. */
    private function vaciar(): void
    {
        foreach (['exenciones_cuota', 'movimientos', 'villa_historial', 'encargados', 'villas', 'propietarios'] as $tabla) {
            DB::table($tabla)->delete();
        }
        DB::table('correlativos')->whereIn('tipo', ['CA', 'CR'])->update(['siguiente' => 1]);
    }

    /**
     * Conceptos a crear si no existen: los del sistema y, de los propios de la importacion (Saldo
     * inicial, Ajustes...), solo los que el plan usa, para no dejar conceptos de mas.
     *
     * @return array<string, bool> DESCR -> ES_CARGO
     */
    private function conceptosNecesarios(array $plan): array
    {
        $usados = [];
        foreach ($plan['villas'] as $v) {
            foreach ($v['movimientos'] as $m) {
                $usados[$m['concepto']] = true;
            }
        }

        return array_intersect_key(self::CONCEPTOS_IMPORTACION, $usados) + ['Cargo extraordinario' => true, 'Mora' => true, 'Abono / Pago' => false];
    }

    /** @return array<string, int> DESCR (o la marca de mantenimiento) -> NUM_CPTO */
    private function asegurarConceptos(array $plan): array
    {
        foreach ($this->conceptosNecesarios($plan) as $descr => $esCargo) {
            Concepto::firstOrCreate(['DESCR' => $descr], [
                'ES_CARGO' => $esCargo,
                'ACTIVO' => ! array_key_exists($descr, self::CONCEPTOS_IMPORTACION),
            ]);
        }
        $mantenimiento = Concepto::mantenimiento() ?? throw new \RuntimeException('No existe el concepto de cuota de mantenimiento.');

        $sinTildes = fn (string $s) => strtr(mb_strtolower(trim($s)), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
        $energia = Concepto::all()->first(fn (Concepto $c) => $sinTildes($c->DESCR) === $sinTildes(self::NOMBRE_ENERGIA))
            ?? Concepto::create(['DESCR' => self::NOMBRE_ENERGIA, 'ES_CARGO' => true, 'ACTIVO' => true]);

        return Concepto::pluck('NUM_CPTO', 'DESCR')->all() + [
            self::MANTENIMIENTO => $mantenimiento->NUM_CPTO,
            self::ENERGIA => $energia->NUM_CPTO,
        ];
    }

    private function textoBitacora(array $plan): string
    {
        $movs = array_sum(array_map(fn ($v) => count($v['movimientos']), $plan['villas']));
        if (isset($plan['bitacora'])) {
            return mb_substr(sprintf($plan['bitacora'], count($plan['villas']), count($plan['propietarios']), $movs), 0, 255);
        }

        return mb_substr('Importó del Excel de estados de cuenta '.count($plan['villas']).' villas, '
            .count($plan['propietarios']).' propietarios y '.$movs.' movimientos (saldo inicial al '
            .Carbon::parse($plan['corte'])->format('d/m/Y').').', 0, 255);
    }

    // ------------------------------------------------------------------------------ script SQL (Railway)

    /**
     * Script para PostgreSQL (DBeaver). BORRA todas las villas, propietarios, encargados, historial y
     * movimientos (y reinicia los correlativos) y vuelve a cargar todo desde el Excel. Todo va en una sola
     * transaccion: si algo falla, no se borra ni se carga nada.
     */
    public function sql(array $plan): string
    {
        $q = fn (?string $s) => $s === null ? 'NULL' : "'".str_replace("'", "''", $s)."'";
        $n = fn ($x) => $x === null ? 'NULL' : number_format((float) $x, 2, '.', '');
        $codigos = implode(', ', array_map($q, array_keys($plan['villas'])));
        // comparacion sin mayusculas ni tildes, para encontrar "Energía Eléctrica" aunque se haya escrito distinto
        $normalizado = fn (string $columna) => "lower(translate({$columna}, 'ÁÉÍÓÚáéíóú', 'AEIOUaeiou'))";
        $energia = "(SELECT \"NUM_CPTO\" FROM conceptos WHERE {$normalizado('"DESCR"')} = 'energia electrica' ORDER BY \"NUM_CPTO\" LIMIT 1)";

        $s = [];
        $s[] = '-- Importacion de estados de cuenta desde Excel. Generado '.now()->format('d/m/Y H:i').'.';
        $s[] = '-- ATENCION: BORRA todas las villas, propietarios, encargados, historial y movimientos y los vuelve a cargar.';
        $s[] = '-- Ejecutar completo en DBeaver con Alt+X. Si algo falla, no se borra ni se carga nada (ROLLBACK).';
        $s[] = 'BEGIN;';
        $s[] = 'SET LOCAL search_path TO public;';
        $s[] = "DO \$\$ BEGIN IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'villas' AND column_name = 'OBSERVACION') THEN RAISE EXCEPTION 'Railway todavía no tiene la última versión del sistema (falta la columna OBSERVACION de villas). Espera a que termine el despliegue y vuelve a correr el script.'; END IF; END \$\$;";
        $s[] = "DO \$\$ BEGIN IF NOT EXISTS (SELECT 1 FROM conceptos WHERE \"ES_MANTENIMIENTO\" = true) THEN RAISE EXCEPTION 'No existe el concepto de cuota de mantenimiento.'; END IF; END \$\$;";
        $s[] = '';
        $s[] = '-- limpieza: se borra todo lo de villas y se reinician los correlativos';
        // CASCADE: tambien vacia las tablas que dependen de las villas (ej. exenciones_cuota)
        $s[] = 'TRUNCATE TABLE movimientos, villa_historial, encargados, villas, propietarios RESTART IDENTITY CASCADE;';
        // la tabla se llamaba folio_counters antes de la migracion que la renombro a correlativos:
        // el script sirve con cualquiera de las dos (antes o despues del despliegue)
        $s[] = "DO \$\$ BEGIN IF to_regclass('correlativos') IS NOT NULL THEN UPDATE correlativos SET siguiente = 1 WHERE tipo IN ('CA', 'CR'); ELSE UPDATE folio_counters SET siguiente = 1 WHERE tipo IN ('CA', 'CR'); END IF; END \$\$;";
        $s[] = '';
        $s[] = '-- conceptos que usa la importacion (inactivos si son propios de la importacion)';
        foreach ($this->conceptosNecesarios($plan) as $descr => $esCargo) {
            $activo = array_key_exists($descr, self::CONCEPTOS_IMPORTACION) ? 'false' : 'true';
            $s[] = "INSERT INTO conceptos (\"DESCR\", \"ES_CARGO\", \"ACTIVO\", \"ES_MANTENIMIENTO\") SELECT {$q($descr)}, ".($esCargo ? 'true' : 'false').", {$activo}, false WHERE NOT EXISTS (SELECT 1 FROM conceptos WHERE \"DESCR\" = {$q($descr)});";
        }
        $s[] = "INSERT INTO conceptos (\"DESCR\", \"ES_CARGO\", \"ACTIVO\", \"ES_MANTENIMIENTO\") SELECT {$q(self::NOMBRE_ENERGIA)}, true, true, false WHERE NOT EXISTS (SELECT 1 FROM conceptos WHERE {$normalizado('"DESCR"')} = 'energia electrica');";
        $s[] = '';
        $s[] = 'CREATE TEMP TABLE imp_prop (clave text PRIMARY KEY, id bigint) ON COMMIT DROP;';
        foreach ($plan['propietarios'] as $clave => $p) {
            $s[] = "WITH ins AS (INSERT INTO propietarios (\"NOMBRES\", \"APELLIDOS\", created_at, updated_at) VALUES ({$q($p['NOMBRES'])}, {$q($p['APELLIDOS'])}, now(), now()) RETURNING id) INSERT INTO imp_prop SELECT {$q($clave)}, id FROM ins;";
        }

        $concepto = fn (string $c) => match ($c) {
            self::MANTENIMIENTO => '(SELECT "NUM_CPTO" FROM conceptos WHERE "ES_MANTENIMIENTO" = true ORDER BY "NUM_CPTO" LIMIT 1)',
            self::ENERGIA => $energia,
            default => "(SELECT \"NUM_CPTO\" FROM conceptos WHERE \"DESCR\" = {$q($c)} ORDER BY \"NUM_CPTO\" LIMIT 1)",
        };

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
