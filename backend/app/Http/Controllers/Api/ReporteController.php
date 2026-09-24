<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ExportaArchivos;
use App\Http\Controllers\Controller;
use App\Models\Villa;
use App\Services\ReporteExcelService;
use App\Services\SaldoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    use ExportaArchivos;

    public function __construct(
        private readonly SaldoService $saldoService,
        private readonly ReporteExcelService $excel,
    ) {}

    // --- Estado de cuenta ---

    public function estadoCuenta(Request $request)
    {
        [$reportes] = $this->datosEstadoCuenta($request);

        return response()->json(['data' => $reportes]);
    }

    public function estadoCuentaPdf(Request $request): Response
    {
        [$reportes, $desde, $hasta, $villa] = $this->datosEstadoCuenta($request);

        return $this->pdf('reportes.estado-cuenta', [
            'titulo' => 'Estado de Cuenta',
            'subtitulo' => $this->subtituloEstadoCuenta($desde, $hasta, $villa),
            'reportes' => $reportes,
        ], $this->nombreArchivo('estado-de-cuenta', $villa ?? 'todas', $this->rangoParaArchivo($desde, $hasta), 'pdf'));
    }

    public function exportarEstadoCuentaXlsx(Request $request): StreamedResponse
    {
        [$reportes, $desde, $hasta, $villa] = $this->datosEstadoCuenta($request);

        return $this->descargarXlsx(
            $this->excel->estadoDeCuenta($reportes, $desde, $hasta, $villa, $this->usuarioActual()),
            $this->nombreArchivo('estado-de-cuenta', $villa ?? 'todas', $this->rangoParaArchivo($desde, $hasta), 'xlsx'),
        );
    }

    // --- Saldos Generales / CXC / CXP ---

    /**
     * Reporte "Saldos Generales": listado plano de villa + saldo a una fecha,
     * con filtros opcionales para omitir saldos al dia / a favor.
     */
    public function saldosGenerales(Request $request)
    {
        return response()->json(['data' => $this->datosSaldos($request)['saldos']]);
    }

    public function saldosGeneralesPdf(Request $request): Response
    {
        $d = $this->datosSaldos($request);

        return $this->pdf('reportes.saldos-generales', [
            'titulo' => $d['titulo'],
            'subtitulo' => 'Saldos al '.$d['hasta']->format('d/m/Y'),
            'saldos' => $d['saldos'],
            'total' => $d['saldos']->sum('saldo'),
            'etiquetaTotal' => $d['etiquetaTotal'],
        ], $this->nombreArchivo(Str::slug($d['titulo']), null, $this->fechaParaArchivo($d['hasta']), 'pdf'));
    }

    public function exportarSaldosXlsx(Request $request): StreamedResponse
    {
        $d = $this->datosSaldos($request);

        return $this->descargarXlsx(
            $this->excel->saldosGenerales($d['titulo'], $d['etiquetaTotal'], $d['hasta'], $d['saldos'], $this->usuarioActual()),
            $this->nombreArchivo(Str::slug($d['titulo']), null, $this->fechaParaArchivo($d['hasta']), 'xlsx'),
        );
    }

    // --- Antigüedad de Saldos ---

    /**
     * Reporte "Antigüedad de Saldos": solo villas deudoras, clasificadas en
     * 30/60/90/+90 dias segun el cargo pendiente mas antiguo, con totales y
     * porcentajes sobre el saldo adeudado total.
     */
    public function antiguedadSaldos(Request $request)
    {
        return response()->json($this->saldoService->antiguedadDeSaldos($this->fechaCorte($request)));
    }

    public function antiguedadSaldosPdf(Request $request): Response
    {
        $hasta = $this->fechaCorte($request);

        return $this->pdf('reportes.antiguedad-saldos', [
            'titulo' => 'Antigüedad de Saldos',
            'subtitulo' => 'Saldos al '.$hasta->format('d/m/Y'),
            'antiguedad' => $this->saldoService->antiguedadDeSaldos($hasta),
        ], $this->nombreArchivo('antiguedad-de-saldos', null, $this->fechaParaArchivo($hasta), 'pdf'), 'landscape');
    }

    public function exportarAntiguedadXlsx(Request $request): StreamedResponse
    {
        $hasta = $this->fechaCorte($request);

        return $this->descargarXlsx(
            $this->excel->antiguedadDeSaldos($hasta, $this->saldoService->antiguedadDeSaldos($hasta), $this->usuarioActual()),
            $this->nombreArchivo('antiguedad-de-saldos', null, $this->fechaParaArchivo($hasta), 'xlsx'),
        );
    }

    // --- datos compartidos por las vistas JSON, los PDF y los Excel ---

    /** Fecha de corte (`hasta`) de los reportes que solo reciben esa fecha. */
    private function fechaCorte(Request $request): Carbon
    {
        $data = $request->validate([
            'hasta' => ['required', 'date'],
        ], [
            'hasta.required' => 'Debes indicar la fecha de corte.',
            'hasta.date' => 'La fecha de corte no es válida.',
        ]);

        return Carbon::parse($data['hasta']);
    }

    /**
     * @return array{0: Collection, 1: Carbon, 2: Carbon, 3: ?string}  reportes por villa, desde, hasta y villa filtrada
     */
    private function datosEstadoCuenta(Request $request): array
    {
        $data = $request->validate([
            'villa' => ['nullable', 'string', 'exists:CLIE1,CLV_CLIE'],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
        ], [
            'villa.exists' => 'La villa seleccionada no existe.',
            'desde.required' => 'Debes indicar la fecha de inicio.',
            'desde.date' => 'La fecha de inicio no es válida.',
            'hasta.required' => 'Debes indicar la fecha de corte.',
            'hasta.date' => 'La fecha de corte no es válida.',
            'hasta.after_or_equal' => 'La fecha de corte no puede ser anterior a la fecha de inicio.',
        ]);

        $desde = Carbon::parse($data['desde']);
        $hasta = Carbon::parse($data['hasta']);

        $villas = isset($data['villa'])
            ? Villa::where('CLV_CLIE', $data['villa'])->get()
            : Villa::orderBy('CLV_CLIE')->get();

        $saldos = $this->saldoService->saldosPorVilla();

        $reportes = $villas->map(fn (Villa $v) => [
            'villa' => $v->CLV_CLIE,
            'propietario' => $v->nombre_completo,
            'saldo_actual' => $saldos->get($v->CLV_CLIE, 0.0),
            'estado_cuenta' => $this->saldoService->estadoDeCuenta($v, $desde, $hasta),
        ]);

        return [$reportes, $desde, $hasta, $data['villa'] ?? null];
    }

    private function subtituloEstadoCuenta(Carbon $desde, Carbon $hasta, ?string $villa): string
    {
        return 'Del '.$desde->format('d/m/Y').' al '.$hasta->format('d/m/Y').' · '.($villa ? "Villa {$villa}" : 'Todas las villas');
    }

    /**
     * @return array{titulo: string, etiquetaTotal: string, hasta: Carbon, saldos: Collection}
     */
    private function datosSaldos(Request $request): array
    {
        // omitir_al_dia/omitir_a_favor llegan desde query string como el texto
        // literal "true"/"false" (asi los serializa axios) -- la regla de
        // validacion 'boolean' de Laravel NO acepta esas cadenas (solo
        // true/false/0/1/'0'/'1'), asi que se leen con $request->boolean(),
        // que si las interpreta correctamente, en vez de validarlas como tal.
        $hasta = $this->fechaCorte($request);
        $omitirAlDia = $request->boolean('omitir_al_dia');
        $omitirAFavor = $request->boolean('omitir_a_favor');
        $soloNegativos = $request->boolean('solo_negativos');

        [$titulo, $etiquetaTotal] = match (true) {
            $soloNegativos => ['Cuentas por Pagar (CXP)', 'Total CXP'],
            $omitirAlDia && $omitirAFavor => ['Cuentas por Cobrar (CXC)', 'Total CXC'],
            default => ['Saldos Generales', 'Total general'],
        };

        return [
            'titulo' => $titulo,
            'etiquetaTotal' => $etiquetaTotal,
            'hasta' => $hasta,
            'saldos' => $this->saldoService->saldosGenerales($hasta, $omitirAlDia, $omitirAFavor, $soloNegativos),
        ];
    }

}
