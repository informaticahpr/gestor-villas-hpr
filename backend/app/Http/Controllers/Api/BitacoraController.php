<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ExportaArchivos;
use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Services\ReporteExcelService;
use App\Support\Paginacion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BitacoraController extends Controller
{
    use ExportaArchivos;

    /** Maximo de registros por archivo exportado (protege la memoria de dompdf/PhpSpreadsheet). */
    private const LIMITE_EXPORTACION = 2000;

    private const ENTIDADES = [
        'usuario' => 'Usuarios',
        'concepto' => 'Conceptos',
        'forma_pago' => 'Formas de pago',
        'cuota_especial' => 'Cuotas especiales',
        'cuota_mantenimiento' => 'Cuota de mantenimiento',
    ];

    public function __construct(private readonly ReporteExcelService $excel) {}

    public function index(Request $request)
    {
        $request->validate(Paginacion::reglas(), Paginacion::mensajes());

        $pagina = $this->consulta($request)->paginate(Paginacion::porPagina($request), ['*'], 'pagina', $request->integer('pagina', 1));

        return response()->json([
            'data' => $pagina->getCollection()->map(fn (Bitacora $b) => [
                'id' => $b->id,
                'usuario' => $b->usuario?->name ?? 'Sistema',
                'entidad' => $b->entidad,
                'accion' => $b->accion,
                'descripcion' => $b->descripcion,
                'fecha' => $b->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
                'ultima_pagina' => $pagina->lastPage(),
                'limite_exportacion' => self::LIMITE_EXPORTACION,
            ],
        ]);
    }

    public function exportarExcel(Request $request): StreamedResponse
    {
        [$registros, $subtitulo] = $this->datosExportacion($request);

        return $this->descargarXlsx(
            $this->excel->bitacora($registros, $subtitulo, $this->usuarioActual()),
            $this->nombreArchivo('bitacora', null, $this->fechaParaArchivo(now()), 'xlsx'),
        );
    }

    public function exportarPdf(Request $request): Response
    {
        [$registros, $subtitulo] = $this->datosExportacion($request);

        return $this->pdf('reportes.bitacora', [
            'titulo' => 'Bitácora de Cambios',
            'subtitulo' => $subtitulo,
            'registros' => $registros,
        ], $this->nombreArchivo('bitacora', null, $this->fechaParaArchivo(now()), 'pdf'));
    }

    /**
     * Consulta filtrada (sin paginar), ordenada del mas reciente al mas antiguo.
     * El `id` desempata registros con la misma fecha para que las paginas sean estables.
     */
    private function consulta(Request $request): Builder
    {
        $data = $request->validate([
            'entidad' => ['nullable', 'string'],
            'accion' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:255'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ], [
            'hasta.after_or_equal' => 'La fecha "hasta" no puede ser anterior a "desde".',
        ]);

        $query = Bitacora::with('usuario')->orderByDesc('created_at')->orderByDesc('id');

        if (! empty($data['entidad'])) {
            $query->where('entidad', $data['entidad']);
        }
        if (! empty($data['accion'])) {
            $query->where('accion', $data['accion']);
        }
        if (! empty($data['q'])) {
            $query->where('descripcion', 'like', '%'.$data['q'].'%');
        }
        if (! empty($data['desde'])) {
            $query->whereDate('created_at', '>=', $data['desde']);
        }
        if (! empty($data['hasta'])) {
            $query->whereDate('created_at', '<=', $data['hasta']);
        }

        return $query;
    }

    /**
     * Todos los registros que coinciden con los filtros (no solo la pagina en pantalla), listos
     * para PDF/Excel, y un texto que resume los filtros aplicados para el subtitulo.
     *
     * @return array{0: Collection, 1: string}
     */
    private function datosExportacion(Request $request): array
    {
        $query = $this->consulta($request);

        $total = (clone $query)->count();
        if ($total > self::LIMITE_EXPORTACION) {
            abort(422, "Hay {$total} registros con estos filtros y el máximo por archivo es ".self::LIMITE_EXPORTACION.'. Acota el rango de fechas.');
        }

        $registros = $query->get()->map(fn (Bitacora $b) => [
            'fecha' => $b->created_at,
            'usuario' => $b->usuario?->name ?? 'Sistema',
            'cambio' => ucfirst($b->accion).' · '.(self::ENTIDADES[$b->entidad] ?? $b->entidad),
            'descripcion' => $b->descripcion,
        ]);

        return [$registros, $this->resumenFiltros($request)];
    }

    private function resumenFiltros(Request $request): string
    {
        $partes = [];

        $desde = $request->filled('desde') ? Carbon::parse($request->input('desde'))->format('d/m/Y') : null;
        $hasta = $request->filled('hasta') ? Carbon::parse($request->input('hasta'))->format('d/m/Y') : null;
        if ($desde && $hasta) {
            $partes[] = "Del {$desde} al {$hasta}";
        } elseif ($desde) {
            $partes[] = "Desde {$desde}";
        } elseif ($hasta) {
            $partes[] = "Hasta {$hasta}";
        }

        if ($request->filled('entidad')) {
            $partes[] = 'Tipo: '.(self::ENTIDADES[$request->input('entidad')] ?? $request->input('entidad'));
        }
        if ($request->filled('accion')) {
            $partes[] = 'Acción: '.ucfirst($request->input('accion'));
        }
        if ($request->filled('q')) {
            $partes[] = 'Búsqueda: "'.$request->input('q').'"';
        }

        return $partes ? implode(' · ', $partes) : 'Todos los registros';
    }
}
