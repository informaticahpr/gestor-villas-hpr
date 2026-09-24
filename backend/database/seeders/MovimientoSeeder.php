<?php

namespace Database\Seeders;

use App\Models\Movimiento;
use App\Models\User;
use App\Models\Villa;
use App\Services\SaldoService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class MovimientoSeeder extends Seeder
{
    private const NUM_CPTO_CUOTA = 1;

    private const NUM_CPTO_EXTRA = 2;

    private const NUM_CPTO_ABONO = 4;

    public function run(SaldoService $saldoService): void
    {
        $usuarioId = User::where('email', 'director@hpr.test')->value('id');

        foreach (VillaSeeder::definiciones() as $d) {
            $mes = Carbon::parse($d['inicio'])->startOfMonth();
            $fin = Carbon::now()->startOfMonth();
            $mesesImpagos = $d['meses_impagos'] ?? [];
            $pagoParcial = $d['pago_parcial'] ?? null;
            $impagoDesde = isset($d['impago_desde']) ? $d['impago_desde'] : null;

            while ($mes->lte($fin)) {
                $clave = $mes->format('Y-m');

                Movimiento::create([
                    'CLV_CLIE' => $d['id'],
                    'NUM_CPTO' => self::NUM_CPTO_CUOTA,
                    'IMPORTE' => $d['cuota'],
                    'FECHA_APLI' => $mes->copy()->day(1),
                    'FECHA_VENC' => $mes->copy()->day(15),
                    'ANIO' => $mes->year,
                    'MES' => $mes->month,
                    'OBS' => 'Cuota de mantenimiento '.$mes->translatedFormat('F Y'),
                    'USUARIO_ID' => $usuarioId,
                ]);

                if ($pagoParcial && $pagoParcial['mes'] === $clave) {
                    Movimiento::create([
                        'CLV_CLIE' => $d['id'],
                        'NUM_CPTO' => self::NUM_CPTO_ABONO,
                        'IMPORTE' => $pagoParcial['pagado'],
                        'FECHA_APLI' => $mes->copy()->day(5),
                        'ANIO' => $mes->year,
                        'MES' => $mes->month,
                        'OBS' => 'Abono parcial cuota '.$mes->translatedFormat('F Y'),
                        'USUARIO_ID' => $usuarioId,
                    ]);
                } elseif ($impagoDesde && $clave >= $impagoDesde) {
                    // sin abono: deuda continua desde este mes en adelante
                } elseif (! in_array($clave, $mesesImpagos, true)) {
                    Movimiento::create([
                        'CLV_CLIE' => $d['id'],
                        'NUM_CPTO' => self::NUM_CPTO_ABONO,
                        'IMPORTE' => $d['cuota'],
                        'FECHA_APLI' => $mes->copy()->day(5),
                        'ANIO' => $mes->year,
                        'MES' => $mes->month,
                        'OBS' => 'Pago cuota '.$mes->translatedFormat('F Y'),
                        'USUARIO_ID' => $usuarioId,
                    ]);
                }

                $mes->addMonth();
            }

            if (isset($d['cargo_extra'])) {
                $fechaExtra = Carbon::now()->subMonth()->day(15);
                Movimiento::create([
                    'CLV_CLIE' => $d['id'],
                    'NUM_CPTO' => self::NUM_CPTO_EXTRA,
                    'IMPORTE' => $d['cargo_extra'],
                    'FECHA_APLI' => $fechaExtra,
                    'FECHA_VENC' => $fechaExtra->copy()->addDays(15),
                    'ANIO' => $fechaExtra->year,
                    'MES' => $fechaExtra->month,
                    'OBS' => 'Cargo extraordinario (reparación de acceso)',
                    'USUARIO_ID' => $usuarioId,
                ]);
            }

            if (isset($d['abono_extra'])) {
                $fechaAbono = Carbon::now()->subDays(10);
                Movimiento::create([
                    'CLV_CLIE' => $d['id'],
                    'NUM_CPTO' => self::NUM_CPTO_ABONO,
                    'IMPORTE' => $d['abono_extra'],
                    'FECHA_APLI' => $fechaAbono,
                    'ANIO' => $fechaAbono->year,
                    'MES' => $fechaAbono->month,
                    'OBS' => 'Abono adelantado',
                    'USUARIO_ID' => $usuarioId,
                ]);
            }
        }

        foreach (Villa::all() as $villa) {
            $saldoService->recalcularSaldo($villa);
        }
    }
}
