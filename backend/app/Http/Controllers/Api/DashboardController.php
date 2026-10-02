<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Concepto;
use App\Models\Movimiento;
use App\Models\Villa;
use App\Services\SaldoService;
use Carbon\Carbon;

/**
 * Indicadores del inicio (solo Director/Admin): villas registradas, cuota de mantenimiento,
 * cuentas por cobrar y saldo a favor a la fecha, y lo recuperado en el mes y en el año.
 *
 * "Recuperado" = creditos/abonos aplicados en el periodo, comparados contra los cargos (cuentas
 * por cobrar) generados en ese mismo periodo. Puede pasar del 100% cuando se cobra deuda de
 * periodos anteriores.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly SaldoService $saldoService) {}

    public function __invoke()
    {
        $hoy = Carbon::today();
        $saldos = $this->saldoService->saldosPorVilla($hoy);
        $cuota = Concepto::mantenimiento()?->MONTO_DEFAULT;

        // Movimientos del año hasta hoy; se agrupan por mes aqui (no en SQL) para no depender de
        // funciones de fecha distintas entre PostgreSQL y otros motores.
        $movimientos = Movimiento::query()
            ->join('conceptos', 'conceptos.NUM_CPTO', '=', 'movimientos.NUM_CPTO')
            ->whereBetween('movimientos.FECHA_APLI', [$hoy->copy()->startOfYear(), $hoy])
            ->get(['movimientos.FECHA_APLI', 'movimientos.IMPORTE', 'conceptos.ES_CARGO']);

        $meses = collect(range(1, $hoy->month))->mapWithKeys(fn ($m) => [$m => ['mes' => $m, 'cxc' => 0.0, 'recuperado' => 0.0]])->all();

        foreach ($movimientos as $mov) {
            $mes = $mov->FECHA_APLI->month;
            $meses[$mes][$mov->ES_CARGO ? 'cxc' : 'recuperado'] += (float) $mov->IMPORTE;
        }

        $meses = collect($meses)->map(fn ($m) => [
            'mes' => $m['mes'],
            'cxc' => round($m['cxc'], 2),
            'recuperado' => round($m['recuperado'], 2),
        ])->values();

        $delMes = $meses->last();

        return response()->json([
            'fecha' => $hoy->toDateString(),
            'villas' => Villa::count(),
            'cuota_mantenimiento' => $cuota !== null ? (float) $cuota : null,
            'cuentas_por_cobrar' => round($saldos->filter(fn ($s) => $s > 0)->sum(), 2),
            'saldo_a_favor' => round(abs($saldos->filter(fn ($s) => $s < 0)->sum()), 2),
            'mes' => [
                'cxc' => $delMes['cxc'],
                'recuperado' => $delMes['recuperado'],
            ],
            'anio' => [
                'anio' => $hoy->year,
                'cxc' => round($meses->sum('cxc'), 2),
                'recuperado' => round($meses->sum('recuperado'), 2),
                'meses' => $meses,
            ],
        ]);
    }
}
