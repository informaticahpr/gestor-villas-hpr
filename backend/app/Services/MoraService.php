<?php

namespace App\Services;

use App\Models\Concepto;
use App\Models\Movimiento;
use App\Models\Parametro;
use App\Models\User;
use App\Models\Villa;
use App\Support\Formato;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mora mensual:
 * - A partir del dia 5 de cada mes, a cada villa con "Aplicar cuota mensual" que tenga deuda ANTERIOR
 *   se le carga una mora = deuda anterior pendiente x porcentaje de mora (Configuracion, 0.83 %).
 *   Deuda anterior = lo que debe al dia 5 menos los cargos de ese mes (la cuota del dia 1 no genera
 *   mora ese mismo mes). Ej.: debe $300 al 31/10, el 01/11 se carga la cuota de $160 -> mora de
 *   noviembre = $300 x 0.83 %. Lo que pague entre el 1 y el 5 reduce la deuda anterior. Incluye las
 *   moras de meses anteriores. Una sola por villa y mes (MORA_PERIODO).
 * - Si antes de que termine ese mes paga todo lo que debia, la mora de ese mes se anula sola.
 * - Arreglo de pago: Director/Admin pueden anular moras de una villa (ver anularPorArreglo()).
 */
class MoraService
{
    public const DIA_APLICACION = 5;

    public function __construct(
        private readonly SaldoService $saldoService,
        private readonly FolioService $folioService,
    ) {}

    /**
     * Aplica la mora del mes de $hoy a las villas que deben. No hace nada antes del dia 5, y no duplica:
     * una villa que ya tiene (o tuvo y se le anulo) la mora de ese mes no recibe otra. Asi se puede
     * correr todos los dias (o de nuevo si el servidor estuvo apagado el dia 5).
     *
     * @return array{periodo: string, aplicadas: int, total: float, porcentaje: float}
     */
    public function aplicarDelMes(?Carbon $hoy = null): array
    {
        $hoy ??= Carbon::today();
        $periodo = $hoy->format('Y-m');
        $porcentaje = Parametro::porcentajeMora();
        $resultado = ['periodo' => $periodo, 'aplicadas' => 0, 'total' => 0.0, 'porcentaje' => $porcentaje];

        if ($hoy->day < self::DIA_APLICACION || $porcentaje <= 0) {
            return $resultado;
        }
        $concepto = Concepto::mora() ?? throw new \RuntimeException('No existe el concepto de Mora (marcado ES_MORA).');

        $fecha = $hoy->copy()->day(self::DIA_APLICACION);
        $saldos = $this->saldoService->saldosPorVilla($fecha);
        $cargosDelMes = $this->cargosDelMes($fecha);
        $yaTienen = Movimiento::conAnulados()->where('MORA_PERIODO', $periodo)->pluck('CLV_CLIE')->flip();

        foreach (Villa::where('APLICOBRO', true)->get() as $villa) {
            // deuda anterior: lo que debe al dia 5 sin los cargos de este mes (la cuota del dia 1)
            $base = round((float) $saldos->get($villa->CLV_CLIE, 0.0) - (float) $cargosDelMes->get($villa->CLV_CLIE, 0.0), 2);
            if ($base <= 0 || $yaTienen->has($villa->CLV_CLIE)) {
                continue;
            }
            $importe = round($base * $porcentaje / 100, 2);
            if ($importe < 0.01) {
                continue;
            }

            DB::transaction(function () use ($villa, $concepto, $fecha, $periodo, $importe, $base, $porcentaje) {
                Movimiento::create([
                    'CLV_CLIE' => $villa->CLV_CLIE,
                    'NUM_CPTO' => $concepto->NUM_CPTO,
                    'IMPORTE' => $importe,
                    'FECHA_APLI' => $fecha->toDateString(),
                    'FECHA_VENC' => $fecha->toDateString(),
                    'ANIO' => $fecha->year,
                    'MES' => $fecha->month,
                    'OBS' => 'Mora de '.$this->nombreMes($fecha).': '.Formato::numero($porcentaje).'% sobre '.Formato::monto($base).' de saldo anterior pendiente',
                    'FOLIO' => $this->folioService->siguiente(true),
                    'MORA_PERIODO' => $periodo,
                ]);
                $this->saldoService->recalcularSaldo($villa);
            });

            $resultado['aplicadas']++;
            $resultado['total'] = round($resultado['total'] + $importe, 2);
        }

        return $resultado;
    }

    /**
     * Despues de registrar un pago: si en el mismo mes de la mora (antes de que termine) la villa ya
     * pago todo lo que debia sin contar esa mora, la mora se anula sola. Devuelve la mora anulada, si hubo.
     */
    public function anularSiPagoAntesDeFinDeMes(Villa $villa, ?Carbon $hoy = null): ?Movimiento
    {
        $hoy ??= Carbon::today();
        $mora = Movimiento::where('CLV_CLIE', $villa->CLV_CLIE)->where('MORA_PERIODO', $hoy->format('Y-m'))->first();
        if (! $mora) {
            return null;
        }

        $saldoSinMora = $this->saldoService->saldoDeVilla($villa, $hoy) - (float) $mora->IMPORTE;
        if ($saldoSinMora > 0.009) {
            return null;
        }

        $mora->anular(null, 'Pagó todo lo adeudado antes de fin de mes: la mora se anula automáticamente.');
        $this->saldoService->recalcularSaldo($villa);

        return $mora;
    }

    /** Moras vigentes (no anuladas) de una villa, de la mas antigua a la mas reciente. */
    public function morasVigentes(Villa $villa): Collection
    {
        return Movimiento::where('CLV_CLIE', $villa->CLV_CLIE)
            ->whereHas('concepto', fn ($q) => $q->where('ES_MORA', true))
            ->orderBy('FECHA_APLI')->orderBy('ID_MOV')
            ->get();
    }

    /**
     * Arreglo de pago (Director/Admin): anula las moras indicadas de la villa.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Movimiento> las moras anuladas
     */
    public function anularPorArreglo(Villa $villa, array $ids, User $usuario, string $motivo): Collection
    {
        return DB::transaction(function () use ($villa, $ids, $usuario, $motivo) {
            $moras = $this->morasVigentes($villa)->whereIn('ID_MOV', $ids)->values();
            foreach ($moras as $mora) {
                $mora->anular($usuario, 'Arreglo de pago: '.$motivo);
            }
            $this->saldoService->recalcularSaldo($villa);

            return $moras;
        });
    }

    /**
     * Cargos (vigentes) de cada villa desde el dia 1 del mes hasta $fecha, sin contar moras.
     *
     * @return Collection<string, float> [CLV_CLIE => total]
     */
    private function cargosDelMes(Carbon $fecha): Collection
    {
        return Movimiento::query()
            ->join('conceptos', 'conceptos.NUM_CPTO', '=', 'movimientos.NUM_CPTO')
            ->where('conceptos.ES_CARGO', true)
            ->where('conceptos.ES_MORA', false)
            ->whereDate('movimientos.FECHA_APLI', '>=', $fecha->copy()->startOfMonth()->toDateString())
            ->whereDate('movimientos.FECHA_APLI', '<=', $fecha->toDateString())
            ->get(['movimientos.CLV_CLIE', 'movimientos.IMPORTE'])
            ->groupBy('CLV_CLIE')
            ->map(fn ($filas) => round((float) $filas->sum('IMPORTE'), 2));
    }

    private function nombreMes(Carbon $fecha): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return $meses[$fecha->month - 1].' '.$fecha->year;
    }
}
