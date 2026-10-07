<?php

namespace App\Services;

use App\Models\ExencionCuota;
use App\Models\Movimiento;
use App\Models\Villa;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SaldoService
{
    /**
     * Cargos menos abonos, acumulado hasta la fecha dada (por defecto, hoy).
     */
    public function saldoDeVilla(Villa $villa, ?Carbon $hasta = null): float
    {
        $hasta ??= Carbon::today();

        $cargos = Movimiento::where('CLV_CLIE', $villa->CLV_CLIE)
            ->where('FECHA_APLI', '<=', $hasta)
            ->whereHas('concepto', fn ($q) => $q->where('ES_CARGO', true))
            ->sum('IMPORTE');

        $abonos = Movimiento::where('CLV_CLIE', $villa->CLV_CLIE)
            ->where('FECHA_APLI', '<=', $hasta)
            ->whereHas('concepto', fn ($q) => $q->where('ES_CARGO', false))
            ->sum('IMPORTE');

        return round($cargos - $abonos, 2);
    }

    /**
     * Saldo de todas las villas a la fecha dada (por defecto, hoy) en una sola consulta:
     * [CLV_CLIE => saldo]. Las villas sin movimientos no aparecen (su saldo es 0).
     *
     * Es el saldo que muestran las pantallas y se calcula al momento, no se lee de villas.SALDO:
     * una columna guardada no cambia por si sola cuando llega la fecha de un cargo programado
     * (ej. uno con fecha de manana), y quedaria desactualizada respecto a los reportes.
     *
     * @return Collection<string, float>
     */
        public function saldosPorVilla(?Carbon $hasta = null): Collection
    {
        $hasta ??= Carbon::today();

        $g = DB::getQueryGrammar();

        return Movimiento::query()
            ->join('conceptos', 'conceptos.NUM_CPTO', '=', 'movimientos.NUM_CPTO')
            ->where('movimientos.FECHA_APLI', '<=', $hasta)
            ->groupBy('movimientos.CLV_CLIE')
            // wrap() pone las comillas correctas: en PostgreSQL las tablas/columnas en mayusculas
            // se crean entre comillas y sin ellas Postgres las busca en minusculas y falla.
            ->selectRaw(sprintf(
                '%s as villa, SUM(CASE WHEN %s = true THEN %s ELSE -%s END) as saldo',
                $g->wrap('movimientos.CLV_CLIE'),
                $g->wrap('conceptos.ES_CARGO'),
                $g->wrap('movimientos.IMPORTE'),
                $g->wrap('movimientos.IMPORTE'),
            ))
            ->get()
            ->mapWithKeys(fn ($fila) => [$fila->villa => round((float) $fila->saldo, 2)]);
    }

    public function recalcularSaldo(Villa $villa): Villa
    {
        $villa->SALDO = $this->saldoDeVilla($villa);
        $villa->save();

        return $villa;
    }

    /**
     * Movimientos de una villa en un rango de fechas, con saldo corrido (running balance).
     */
    public function estadoDeCuenta(Villa $villa, Carbon $desde, Carbon $hasta): Collection
    {
        $saldoInicial = $this->saldoDeVilla($villa, $desde->copy()->subDay());

        $movimientos = Movimiento::with('concepto')
            ->where('CLV_CLIE', $villa->CLV_CLIE)
            // por dia (whereDate): en SQLite la fecha puede estar guardada con o sin hora, y un
            // whereBetween dejaba fuera el primer o el ultimo dia del rango
            ->whereDate('FECHA_APLI', '>=', $desde->toDateString())
            ->whereDate('FECHA_APLI', '<=', $hasta->toDateString())
            ->orderBy('FECHA_APLI')
            ->orderBy('ID_MOV')
            ->get();

        // meses en que no se le cobro la cuota por estar alquilada a producción: lineas informativas, sin
        // monto (no cambian el saldo), despues de los movimientos de ese mismo dia
        $exenciones = ExencionCuota::where('CLV_CLIE', $villa->CLV_CLIE)
            ->whereDate('FECHA', '>=', $desde->toDateString())
            ->whereDate('FECHA', '<=', $hasta->toDateString())
            ->orderBy('FECHA')
            ->get();

        $saldoCorrido = $saldoInicial;
        $filas = collect();
        $agregarExencionesHasta = function (?string $fecha) use (&$exenciones, &$filas, &$saldoCorrido) {
            while ($exenciones->isNotEmpty() && ($fecha === null || $exenciones->first()->FECHA->toDateString() < $fecha)) {
                $ex = $exenciones->shift();
                $filas->push([
                    'id' => null,
                    'correlativo' => null,
                    'tipo' => 'exencion',
                    'fecha' => $ex->FECHA->toDateString(),
                    'descripcion' => $ex->MOTIVO,
                    'observacion' => 'Cuota de mantenimiento de '.$ex->FECHA->locale('es')->translatedFormat('F Y').' no cobrada.',
                    'cargo' => 0,
                    'credito' => 0,
                    'saldo' => round($saldoCorrido, 2),
                ]);
            }
        };

        foreach ($movimientos as $mov) {
            $agregarExencionesHasta($mov->FECHA_APLI->toDateString());
            $esCargo = (bool) $mov->concepto->ES_CARGO;
            $saldoCorrido += $esCargo ? $mov->IMPORTE : -$mov->IMPORTE;

            $filas->push([
                'id' => $mov->ID_MOV,
                'correlativo' => $mov->CORRELATIVO,
                'tipo' => $esCargo ? 'cargo' : 'credito',
                'fecha' => $mov->FECHA_APLI->toDateString(),
                'descripcion' => $mov->concepto->DESCR,
                'observacion' => $mov->OBS,
                'cargo' => $esCargo ? (float) $mov->IMPORTE : 0,
                'credito' => $esCargo ? 0 : (float) $mov->IMPORTE,
                'saldo' => round($saldoCorrido, 2),
            ]);
        }
        $agregarExencionesHasta(null);

        return collect([
            'saldo_inicial' => round($saldoInicial, 2),
            'movimientos' => $filas,
            'saldo_final' => round($saldoCorrido, 2),
        ]);
    }

    /**
     * Saldo de todas las villas al final del rango, con filtros opcionales.
     */
    public function saldosGenerales(Carbon $hasta, bool $omitirAlDia = false, bool $omitirAFavor = false, bool $soloNegativos = false): Collection
    {
        return Villa::todasEnOrden()->map(function (Villa $villa) use ($hasta) {
            return [
                'villa' => $villa->CLV_CLIE,
                'propietario' => $villa->nombre_completo,
                'saldo' => $this->saldoDeVilla($villa, $hasta),
            ];
        })->filter(function (array $fila) use ($omitirAlDia, $omitirAFavor, $soloNegativos) {
            if ($soloNegativos) {
                return $fila['saldo'] < 0;
            }
            if ($omitirAlDia && $fila['saldo'] == 0.0) {
                return false;
            }
            if ($omitirAFavor && $fila['saldo'] < 0) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * Para cada villa deudora, ubica la antiguedad del cargo pendiente mas viejo (FIFO: los
     * abonos se aplican primero a los cargos mas antiguos) y clasifica el saldo pendiente
     * completo en el bucket de antiguedad correspondiente a esa fecha.
     */
    public function antiguedadDeSaldos(?Carbon $hasta = null): array
    {
        $hasta ??= Carbon::today();

        $buckets = [
            'd30' => 0.0,
            'd60' => 0.0,
            'd90' => 0.0,
            'd90mas' => 0.0,
        ];

        $filas = collect();

        foreach (Villa::todasEnOrden() as $villa) {
            $saldo = $this->saldoDeVilla($villa, $hasta);

            if ($saldo <= 0) {
                continue; // al dia o con saldo a favor, no aplica antiguedad de deuda
            }

            $cargos = Movimiento::where('CLV_CLIE', $villa->CLV_CLIE)
                ->where('FECHA_APLI', '<=', $hasta)
                ->whereHas('concepto', fn ($q) => $q->where('ES_CARGO', true))
                ->orderBy('FECHA_APLI')
                ->get(['IMPORTE', 'FECHA_APLI', 'FECHA_VENC'])
                ->map(fn ($m) => [
                    'restante' => (float) $m->IMPORTE,
                    // la antiguedad de la deuda se cuenta desde el vencimiento si existe;
                    // si el cargo no tiene fecha de vencimiento definida, se usa la fecha de aplicacion
                    'fecha' => $m->FECHA_VENC ?? $m->FECHA_APLI,
                ]);

            $abonosDisponibles = (float) Movimiento::where('CLV_CLIE', $villa->CLV_CLIE)
                ->where('FECHA_APLI', '<=', $hasta)
                ->whereHas('concepto', fn ($q) => $q->where('ES_CARGO', false))
                ->sum('IMPORTE');

            $fechaCargoMasViejoPendiente = null;

            foreach ($cargos as $cargo) {
                if ($abonosDisponibles >= $cargo['restante']) {
                    $abonosDisponibles -= $cargo['restante'];

                    continue;
                }

                $fechaCargoMasViejoPendiente = $cargo['fecha'];
                break;
            }

            // si la fecha (de vencimiento o aplicacion) todavia no ha pasado, no cuenta como
            // dias de mora todavia -- evita que diffInDays() de un valor absoluto enganoso
            $dias = ($fechaCargoMasViejoPendiente && $fechaCargoMasViejoPendiente->lte($hasta))
                ? $fechaCargoMasViejoPendiente->diffInDays($hasta)
                : 0;

            $bucket = match (true) {
                $dias > 90 => 'd90mas',
                $dias > 60 => 'd90',
                $dias > 30 => 'd60',
                default => 'd30',
            };

            $buckets[$bucket] += $saldo;

            $filas->push([
                'villa' => $villa->CLV_CLIE,
                'propietario' => $villa->nombre_completo,
                'dias' => $dias,
                'bucket' => $bucket,
                'saldo' => $saldo,
            ]);
        }

        $totalSaldo = array_sum($buckets);

        $porcentajes = collect($buckets)->map(
            fn ($monto) => $totalSaldo > 0 ? round($monto / $totalSaldo * 100, 1) : 0.0
        );

        return [
            'filas' => $filas->values(),
            'totales' => $buckets,
            'porcentajes' => $porcentajes,
            'total_saldo' => round($totalSaldo, 2),
        ];
    }
}
