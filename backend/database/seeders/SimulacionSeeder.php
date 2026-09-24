<?php

namespace Database\Seeders;

use App\Models\Concepto;
use App\Models\FormaPago;
use App\Models\Movimiento;
use App\Models\User;
use App\Models\Villa;
use App\Services\FolioService;
use App\Services\SaldoService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Simulacion para revisar reportes y exportaciones (PDF/Excel): agrega villas nuevas con nombres y
 * datos de relleno, y siete meses de historial de cargos y abonos para TODAS las villas, con
 * escenarios variados: al dia, pagos tardios con mora, atrasos de 1, 2 y 3+ meses (los cuatro
 * tramos del reporte de antiguedad), pagos parciales, trimestrales y bimestrales, pagos adelantados
 * (saldo a favor), cuotas especiales, cargos extraordinarios y villas exentas o sin movimientos.
 *
 * Uso:   php artisan db:seed --class=SimulacionSeeder
 *
 * Es de desarrollo: NO forma parte de DatabaseSeeder. Es determinista (misma semilla, mismos datos)
 * y se niega a correr si ya hay historial anterior al mes actual, para no duplicarlo. Respeta los
 * movimientos que ya existan: el cargo del mes actual que ya se haya aplicado se conserva.
 */
class SimulacionSeeder extends Seeder
{
    /** Meses de historial anteriores al mes actual. */
    private const MESES_HISTORIAL = 7;

    private const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    private const NOMBRES = [
        'Wilmer', 'Yesenia', 'Nelson', 'Gloria', 'Oscar', 'Dilcia', 'Rigoberto', 'Mirian', 'Edwin', 'Suyapa',
        'Franklin', 'Lesly', 'Marvin', 'Iris', 'Darwin', 'Xiomara', 'Kevin', 'Nohelia', 'Melvin', 'Sonia',
        'Héctor', 'Ingrid', 'Ramón', 'Dunia', 'Osman', 'Yolani', 'Gustavo', 'Alba', 'Denis', 'Maribel',
        'Elvin', 'Tania', 'Bayron', 'Lourdes', 'José Luis', 'María Fernanda', 'Juan Carlos', 'Ana Lucía',
        'Luis Alonso', 'María de los Ángeles', 'Karla', 'Jorge', 'Marta', 'Andrés', 'Rosa Elena', 'Cristian',
    ];

    private const APELLIDOS = [
        'Hernández', 'Rodríguez', 'Zelaya', 'Alvarado', 'Banegas', 'Colindres', 'Fúnez', 'Maradiaga', 'Reyes',
        'Cárcamo', 'Escoto', 'Membreño', 'Pineda', 'Lagos', 'Bonilla', 'Cerrato', 'Ordóñez', 'Padilla', 'Turcios',
        'Velásquez', 'Yanes', 'Zúniga', 'Discua', 'Mejía', 'Núñez', 'Oseguera', 'Paz', 'Quezada', 'Salgado',
        'Ulloa', 'Valladares', 'Amaya', 'Benítez', 'Cáceres', 'Durón', 'Espinal', 'Fajardo', 'Galeano', 'Handal',
        'Irías', 'Bustillo', 'Castellanos', 'Midence', 'Rivera', 'Sandoval', 'Tejada',
    ];

    private const EMPRESAS = [
        ['INMOBILIARIA COSTA AZUL S DE RL', 'gerencia@costaazul.example'],
        ['GRUPO PLAYA DORADA', 'admin@playadorada.example'],
        ['HOTELES Y RESORTS DEL CARIBE SA', 'contabilidad@resortscaribe.example'],
    ];

    private const CALLES = [
        'Colonia Las Palmas, Bloque %s, casa %d', 'Residencial El Mirador, casa %2$d', 'Barrio La Ceiba, calle %2$d #%3$d',
        'Boulevard del Sur #%2$d', 'Avenida Los Almendros #%2$d', 'Colonia Bella Vista, Bloque %s, casa %d',
        'Residencial Playa Grande, casa %2$d', 'Calle de los Cocoteros #%2$d',
    ];

    private const DOMINIOS = ['gmail.com', 'hotmail.com', 'yahoo.com', 'outlook.com'];

    /**
     * Comportamiento de cada villa. `desde` = mes (0 = el mas antiguo del historial) en que empezo a
     * pagar cuotas; `especial` = monto de cuota especial; `extras` = cargos extraordinarios.
     *
     * @return array<string, array<string, mixed>>
     */
    private function perfiles(): array
    {
        $extra = fn (int $mes, int $monto, string $obs, string $pago) => ['mes' => $mes, 'monto' => $monto, 'obs' => $obs, 'pago' => $pago];

        return [
            // villas que ya existian
            'A-1' => ['perfil' => 'al_dia'],
            'A-2' => ['perfil' => 'tardio'],
            'A-3' => ['perfil' => 'atraso_2'],
            'A-4' => ['perfil' => 'atraso_3plus'],
            'A-5' => ['perfil' => 'adelantado'],
            'B-1' => ['perfil' => 'parcial'],
            'B-2' => ['perfil' => 'atraso_1'],
            'B-3' => ['perfil' => 'trimestral'],
            'B-4' => ['perfil' => 'al_dia', 'extras' => [$extra(2, 300, 'Reparación de portón de acceso', 'completo')]],
            'B-5' => ['perfil' => 'al_dia'],
            'C-1' => ['perfil' => 'parcial_final'],
            'C-2' => ['perfil' => 'exento', 'extras' => [$extra(3, 150, 'Reposición de luminarias del pasillo', 'completo')]],
            'T-9' => ['perfil' => 'sin_historial'],
            'U-11' => ['perfil' => 'bimestral'],
            'Z-77' => ['perfil' => 'tardio'],
            'Z-8' => ['perfil' => 'al_dia', 'sobrepago_mes' => 3],
            // villas nuevas (nombres y datos aleatorios)
            'D-1' => ['perfil' => 'al_dia'],
            'D-2' => ['perfil' => 'al_dia'],
            'D-3' => ['perfil' => 'tardio', 'desde' => 2],
            'D-4' => ['perfil' => 'atraso_1'],
            'D-5' => ['perfil' => 'atraso_2'],
            'D-6' => ['perfil' => 'atraso_3plus', 'especial' => 75],
            'D-7' => ['perfil' => 'parcial', 'desde' => 2],
            'D-8' => ['perfil' => 'al_dia', 'extras' => [$extra(4, 450, 'Pintura de fachada', 'parcial'), $extra(6, 200, 'Poda y jardinería', 'no')]],
            'E-1' => ['perfil' => 'al_dia'],
            'E-2' => ['perfil' => 'adelantado'],
            'E-3' => ['perfil' => 'al_dia', 'especial' => 90],
            'E-4' => ['perfil' => 'trimestral'],
            'E-5' => ['perfil' => 'tardio', 'desde' => 1],
            'E-6' => ['perfil' => 'atraso_1'],
            'E-7' => ['perfil' => 'al_dia', 'extras' => [$extra(1, 350, 'Cerca perimetral compartida', 'completo')]],
            'E-8' => ['perfil' => 'exento', 'extras' => [$extra(5, 180, 'Mantenimiento de bomba de agua', 'no')]],
            'F-1' => ['perfil' => 'atraso_3plus'],
            'F-2' => ['perfil' => 'al_dia', 'especial' => 110],
            'F-3' => ['perfil' => 'parcial'],
            'F-4' => ['perfil' => 'adelantado'],
            'F-5' => ['perfil' => 'al_dia', 'desde' => 3],
            'F-6' => ['perfil' => 'atraso_2'],
            'F-7' => ['perfil' => 'tardio', 'especial' => 120],
            'F-8' => ['perfil' => 'al_dia', 'desde' => 5],
        ];
    }

    private SaldoService $saldos;

    private FolioService $folios;

    /** @var array<int, array<string, mixed>> */
    private array $filas = [];

    /** @var array<string, int> */
    private array $usuarios = [];

    private int $cuotaActual = 160;

    private int $conceptoCuota;

    private int $conceptoExtra;

    private int $conceptoMora;

    private int $conceptoAbono;

    /** @var list<int> */
    private array $formasPago = [];

    public function run(SaldoService $saldos, FolioService $folios): void
    {
        $this->saldos = $saldos;
        $this->folios = $folios;
        mt_srand(20260921);

        $hoy = Carbon::today();
        $mesActual = $hoy->copy()->startOfMonth();
        $primerMes = $mesActual->copy()->subMonths(self::MESES_HISTORIAL);

        if (Movimiento::where('FECHA_APLI', '<', $mesActual->toDateString())->exists()) {
            $this->command?->error('Ya hay movimientos anteriores al mes actual: la simulación no se ejecuta para no duplicar el historial.');

            return;
        }

        $this->cargarReferencias();
        $this->crearVillasNuevas();

        foreach ($this->perfiles() as $id => $perfil) {
            if (Villa::find($id)) {
                $this->historial($id, $perfil, $primerMes);
                $this->cargoDelMesActual($id, $hoy);
            }
        }

        $this->guardar();

        foreach (Villa::all() as $villa) {
            $this->saldos->recalcularSaldo($villa);
        }

        $this->command?->info(sprintf(
            'Simulación lista: %d villas, %d movimientos nuevos, historial desde %s.',
            Villa::count(),
            count($this->filas),
            $primerMes->format('d/m/Y'),
        ));
    }

    private function cargarReferencias(): void
    {
        $this->cuotaActual = (int) (Concepto::mantenimiento()?->MONTO_DEFAULT ?? 160);
        $this->conceptoCuota = (int) Concepto::mantenimiento()->NUM_CPTO;
        $this->conceptoExtra = (int) Concepto::where('DESCR', 'Cargo extraordinario')->value('NUM_CPTO');
        $this->conceptoMora = (int) Concepto::where('DESCR', 'Mora')->value('NUM_CPTO');
        $this->conceptoAbono = (int) Concepto::where('ES_CARGO', false)->where('DESCR', 'like', 'Abono%')->value('NUM_CPTO');
        $this->formasPago = FormaPago::where('activo', true)->pluck('id')->all();
        $this->usuarios = User::pluck('id', 'name')->all();
    }

    // ------------------------------------------------------------------ villas nuevas

    private function crearVillasNuevas(): void
    {
        $usados = [];
        $empresas = self::EMPRESAS;
        $indice = 0;

        foreach (array_keys($this->perfiles()) as $id) {
            if (! preg_match('/^[DEF]-\d$/', $id) || Villa::find($id)) {
                continue;
            }
            $indice++;
            $perfil = $this->perfiles()[$id];

            // dos villas de "empresa" para probar nombres largos en las tablas y los PDF
            $esEmpresa = in_array($id, ['E-1', 'F-2', 'D-8'], true) && $empresas;
            if ($esEmpresa) {
                [$nombres, $mailEmpresa] = array_shift($empresas);
                $apellidos = '';
            } else {
                do {
                    $nombres = self::NOMBRES[mt_rand(0, count(self::NOMBRES) - 1)];
                    $apellidos = self::APELLIDOS[mt_rand(0, count(self::APELLIDOS) - 1)].' '.self::APELLIDOS[mt_rand(0, count(self::APELLIDOS) - 1)];
                } while (isset($usados["$nombres $apellidos"]));
                $usados["$nombres $apellidos"] = true;
            }

            $nombres = mb_strtoupper($nombres, 'UTF-8');
            $apellidos = mb_strtoupper($apellidos, 'UTF-8');
            $base = Str::slug(($esEmpresa ? '' : $nombres.' ').Str::before($apellidos.' ', ' '), '.');

            $telefono = fn (string $prefijo) => $prefijo.mt_rand(100, 999).'-'.mt_rand(1000, 9999);

            Villa::create([
                'CLV_CLIE' => $id,
                'NOMBRES' => $nombres,
                'APELLIDOS' => $apellidos,
                'DIR' => sprintf(self::CALLES[mt_rand(0, count(self::CALLES) - 1)], chr(mt_rand(65, 70)), mt_rand(1, 60), mt_rand(1, 250)),
                'TELF' => $telefono('2'),
                'CELULAR' => $telefono((string) [3, 8, 9][mt_rand(0, 2)]),
                'OTRO_TEL' => mt_rand(1, 100) <= 35 ? $telefono('9') : null,
                'MAIL' => $esEmpresa ? $mailEmpresa : $base.'@'.self::DOMINIOS[mt_rand(0, 3)],
                'MAIL2' => mt_rand(1, 100) <= 30 ? $base.mt_rand(10, 99).'@'.self::DOMINIOS[mt_rand(0, 3)] : null,
                'FCONTRUC' => Carbon::create(mt_rand(2018, 2025), mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                'NOMED' => 'ENEE-'.(2000 + $indice),
                'FECHA_NAC' => $esEmpresa ? null : Carbon::create(mt_rand(1955, 1996), mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                'NOHAB' => mt_rand(1, 6),
                'NOBATH' => mt_rand(1, 4),
                'APLICOBRO' => $perfil['perfil'] !== 'exento',
                'CUOTA_ESPECIAL' => isset($perfil['especial']),
                'MONTO_CUOTA_ESPECIAL' => $perfil['especial'] ?? null,
                'SALDO' => 0,
            ]);
        }
    }

    // ------------------------------------------------------------------ movimientos

    /**
     * Cuota de mantenimiento de la villa en el mes `$idx` del historial. Antes de los ultimos 4
     * meses la cuota general era $10 menor (un ajuste de tarifa); las villas con cuota especial
     * pagan siempre su monto.
     */
    private function cuota(array $perfil, int $idx): int
    {
        if (isset($perfil['especial'])) {
            return (int) $perfil['especial'];
        }

        return $idx < self::MESES_HISTORIAL - 3 ? $this->cuotaActual - 10 : $this->cuotaActual;
    }

    /** Cuota especial vigente de una villa (la de la base de datos), o null si no tiene. */
    private function especialDe(Villa $villa): ?int
    {
        return $villa->CUOTA_ESPECIAL && $villa->MONTO_CUOTA_ESPECIAL !== null ? (int) $villa->MONTO_CUOTA_ESPECIAL : null;
    }

    private function historial(string $id, array $perfil, Carbon $primerMes): void
    {
        // las villas que ya existian traen su cuota especial de la base de datos
        if ($especial = $this->especialDe(Villa::find($id))) {
            $perfil['especial'] = $especial;
        }

        $tipo = $perfil['perfil'];
        $n = self::MESES_HISTORIAL;
        $desde = $perfil['desde'] ?? 0;
        $cobra = ! in_array($tipo, ['exento', 'sin_historial'], true);
        $corte = $tipo === 'atraso_3plus' ? mt_rand(1, 2) : null; // deja de pagar despues de este mes
        $pagos = []; // pagos pendientes de agrupar: [mes => monto] (trimestral / bimestral)

        if ($cobra) {
            for ($i = $desde; $i < $n; $i++) {
                $mes = $primerMes->copy()->addMonths($i);
                $cuota = $this->cuota($perfil, $i);
                $nombreMes = self::MESES[$mes->month - 1].' '.$mes->year;

                $this->cargo($id, $this->conceptoCuota, $cuota, $mes->copy()->day(1), $mes->copy()->day(10), "Cuota de mantenimiento $nombreMes");
                $pagos[$i] = $cuota;

                switch ($tipo) {
                    case 'al_dia':
                        $sobre = ($perfil['sobrepago_mes'] ?? null) === $i ? 40 : 0;
                        $this->abono($id, $cuota + $sobre, $mes->copy()->day(mt_rand(2, 9)), "Pago cuota $nombreMes".($sobre ? ' (redondeado, queda saldo a favor)' : ''));
                        break;

                    case 'tardio':
                        $mora = mt_rand(1, 100) <= 60 ? 25 : 0;
                        if ($mora) {
                            $this->cargo($id, $this->conceptoMora, $mora, $mes->copy()->day(11), $mes->copy()->day(20), "Mora por pago tardío de $nombreMes");
                        }
                        $this->abono($id, $cuota + $mora, $mes->copy()->day(mt_rand(14, 26)), "Pago cuota $nombreMes".($mora ? ' y mora' : ''));
                        break;

                    case 'atraso_1':
                        if ($i < $n - 1) {
                            $this->abono($id, $cuota, $mes->copy()->day(mt_rand(3, 12)), "Pago cuota $nombreMes");
                        }
                        break;

                    case 'atraso_2':
                        if ($i < $n - 2) {
                            $this->abono($id, $cuota, $mes->copy()->day(mt_rand(3, 12)), "Pago cuota $nombreMes");
                        }
                        break;

                    case 'atraso_3plus':
                        if ($i <= $corte) {
                            $this->abono($id, $cuota, $mes->copy()->day(mt_rand(3, 12)), "Pago cuota $nombreMes");
                        } else {
                            $this->cargo($id, $this->conceptoMora, 30, $mes->copy()->day(11), $mes->copy()->day(20), "Mora por atraso de $nombreMes");
                        }
                        break;

                    case 'parcial':
                        $this->abono($id, (int) round($cuota * 0.6), $mes->copy()->day(mt_rand(5, 15)), "Abono parcial cuota $nombreMes");
                        break;

                    case 'parcial_final':
                        // paga completo, salvo el ultimo mes del historial (abono parcial)
                        $pago = $i === $n - 1 ? (int) round($cuota / 2) : $cuota;
                        $this->abono($id, $pago, $mes->copy()->day(mt_rand(3, 9)), ($i === $n - 1 ? 'Abono parcial cuota ' : 'Pago cuota ').$nombreMes);
                        break;

                    case 'adelantado':
                        if ($i < $n - 4) {
                            $this->abono($id, $cuota, $mes->copy()->day(mt_rand(2, 9)), "Pago cuota $nombreMes");
                        } elseif ($i === $n - 4) {
                            // paga por adelantado los meses que faltan, el mes actual y una cuota de mas
                            $this->abono($id, $cuota * 6, $mes->copy()->day(5), 'Pago adelantado de 6 cuotas (saldo a favor)');
                        }
                        break;

                    case 'trimestral':
                        if (($i - $desde) % 3 === 2) {
                            $this->abono($id, $pagos[$i] + ($pagos[$i - 1] ?? 0) + ($pagos[$i - 2] ?? 0), $mes->copy()->day(15), 'Pago trimestral de cuotas');
                        }
                        break;

                    case 'bimestral':
                        if (($i - $desde) % 2 === 1) {
                            $this->abono($id, $pagos[$i] + ($pagos[$i - 1] ?? 0), $mes->copy()->day(20), 'Pago bimestral de cuotas');
                        }
                        break;
                }
            }
        }

        foreach ($perfil['extras'] ?? [] as $extra) {
            $mes = $primerMes->copy()->addMonths($extra['mes']);
            $fecha = $mes->copy()->day(12);
            $this->cargo($id, $this->conceptoExtra, $extra['monto'], $fecha, $fecha->copy()->addDays(20), $extra['obs']);

            $pago = match ($extra['pago']) {
                'completo' => $extra['monto'],
                'parcial' => (int) round($extra['monto'] / 2),
                default => 0,
            };
            if ($pago > 0) {
                $this->abono($id, $pago, $fecha->copy()->addDays(mt_rand(6, 14)), ($extra['pago'] === 'parcial' ? 'Abono parcial: ' : 'Pago: ').mb_strtolower($extra['obs']));
            }
        }
    }

    /** El cargo del mes actual: se conserva el que ya se haya aplicado; si falta, se agrega con la misma fecha. */
    private function cargoDelMesActual(string $id, Carbon $hoy): void
    {
        $villa = Villa::find($id);
        $perfil = $this->perfiles()[$id];
        if (! $villa->APLICOBRO || in_array($perfil['perfil'], ['exento'], true)) {
            return;
        }

        $inicioMes = $hoy->copy()->startOfMonth();
        $yaTiene = Movimiento::where('CLV_CLIE', $id)->where('NUM_CPTO', $this->conceptoCuota)->where('FECHA_APLI', '>=', $inicioMes)->exists();
        if ($yaTiene) {
            return;
        }

        $modelo = Movimiento::where('NUM_CPTO', $this->conceptoCuota)->where('FECHA_APLI', '>=', $inicioMes)->first();
        $fecha = $modelo ? Carbon::parse($modelo->FECHA_APLI) : $hoy;
        $venc = $modelo?->FECHA_VENC ? Carbon::parse($modelo->FECHA_VENC) : $hoy->copy()->addDays(3);
        $cuota = $this->especialDe($villa) ?? $this->cuotaActual;

        $this->cargo($id, $this->conceptoCuota, $cuota, $fecha, $venc, null, $modelo?->USUARIO_ID, $modelo?->created_at);
    }

    private function cargo(string $villa, int $concepto, int|float $monto, Carbon $fecha, Carbon $venc, ?string $obs, ?int $usuario = null, ?Carbon $creado = null): void
    {
        $this->fila($villa, $concepto, null, $monto, $fecha, $venc, $obs, $usuario ?? $this->usuarioDeCargo($fecha), $creado);
    }

    private function abono(string $villa, int|float $monto, Carbon $fecha, string $obs): void
    {
        $forma = $this->formaDePago();
        $referencia = match ($forma) {
            2 => 'TRF'.mt_rand(10000, 99999),
            3 => 'DEP'.mt_rand(10000, 99999),
            4 => 'CHQ'.mt_rand(1000, 9999),
            default => null,
        };
        if ($referencia && mt_rand(1, 100) <= 75) {
            $obs .= ' — ref. '.$referencia;
        }
        // algunos sin descripcion y otros con un texto largo, para probar como se ajustan en los reportes
        $suerte = mt_rand(1, 100);
        if ($suerte <= 8) {
            $obs = null;
        } elseif ($suerte >= 96) {
            $obs .= '. Pago recibido en oficina de administración, se entrega recibo original al propietario y se archiva copia en el expediente de la villa.';
        }

        $this->fila($villa, $this->conceptoAbono, $forma, $monto, $fecha, null, $obs, $this->usuarioDePago(), null, $referencia);
    }

    private function fila(string $villa, int $concepto, ?int $forma, int|float $monto, Carbon $fecha, ?Carbon $venc, ?string $obs, int $usuario, ?Carbon $creado = null, ?string $referencia = null): void
    {
        $this->filas[] = [
            'CLV_CLIE' => $villa,
            'NUM_CPTO' => $concepto,
            'FORMA_PAGO_ID' => $forma,
            'IMPORTE' => $monto,
            'FECHA_APLI' => $fecha->copy()->startOfDay(),
            'FECHA_VENC' => $venc?->copy()->startOfDay(),
            'ANIO' => $fecha->year,
            'MES' => $fecha->month,
            'REFER' => $referencia,
            'OBS' => $obs,
            'USUARIO_ID' => $usuario,
            // se registra en horario de oficina del dia del movimiento
            'creado' => $creado ?? $fecha->copy()->setTime(mt_rand(8, 17), mt_rand(0, 59), mt_rand(0, 59)),
        ];
    }

    /** Efectivo, transferencia y deposito son los mas comunes. */
    private function formaDePago(): int
    {
        $ids = array_flip($this->formasPago);
        $peso = [1 => 25, 2 => 35, 3 => 22, 4 => 9, 5 => 5, 6 => 4];
        $tirada = mt_rand(1, 100);
        $acumulado = 0;
        foreach ($peso as $id => $p) {
            $acumulado += $p;
            if ($tirada <= $acumulado && isset($ids[$id])) {
                return $id;
            }
        }

        return $this->formasPago[0];
    }

    private function usuarioDeCargo(Carbon $fecha): int
    {
        return $fecha->month % 2 === 0 ? ($this->usuarios['Administrador'] ?? 1) : ($this->usuarios['Supervisor'] ?? 1);
    }

    private function usuarioDePago(): int
    {
        $tirada = mt_rand(1, 100);

        return match (true) {
            $tirada <= 70 => $this->usuarios['Supervisor'] ?? 1,
            $tirada <= 90 => $this->usuarios['Administrador'] ?? 1,
            default => $this->usuarios['Director'] ?? 1,
        };
    }

    /** Ordena cronologicamente, asigna folios en ese orden y guarda. */
    private function guardar(): void
    {
        usort($this->filas, fn ($a, $b) => [$a['FECHA_APLI'], $a['creado']] <=> [$b['FECHA_APLI'], $b['creado']]);

        $lote = [];
        foreach ($this->filas as $f) {
            $creado = $f['creado'];
            unset($f['creado']);
            $f['FOLIO'] = $this->folios->siguiente($f['NUM_CPTO'] !== $this->conceptoAbono);
            $f['FECHA_APLI'] = $f['FECHA_APLI']->format('Y-m-d H:i:s');
            $f['FECHA_VENC'] = $f['FECHA_VENC']?->format('Y-m-d H:i:s');
            $f['created_at'] = $creado->format('Y-m-d H:i:s');
            $f['updated_at'] = $creado->format('Y-m-d H:i:s');
            $lote[] = $f;

            if (count($lote) === 100) {
                Movimiento::insert($lote);
                $lote = [];
            }
        }
        if ($lote) {
            Movimiento::insert($lote);
        }
    }
}
