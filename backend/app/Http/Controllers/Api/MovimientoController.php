<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Concepto;
use App\Models\Movimiento;
use App\Models\Villa;
use App\Services\FolioService;
use App\Services\MoraService;
use App\Services\SaldoService;
use App\Support\Formato;
use App\Support\Paginacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MovimientoController extends Controller
{
    public function __construct(
        private readonly SaldoService $saldoService,
        private readonly FolioService $folioService,
        private readonly MoraService $moraService,
    ) {}

    /**
     * Listado de movimientos (cargos y abonos) para la pantalla de Reimpresion: de aqui se abre el
     * recibo de cada uno. Paginado, del mas reciente al mas antiguo, con filtros opcionales por villa,
     * tipo, folio y rango de fechas del movimiento.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'villa' => ['nullable', 'string', 'exists:villas,CLV_CLIE'],
            'tipo' => ['nullable', 'in:cargo,credito'],
            'q' => ['nullable', 'string', 'max:40'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            ...Paginacion::reglas(),
        ], [
            'villa.exists' => 'La villa seleccionada no existe.',
            'tipo.in' => 'El tipo debe ser cargo o crédito.',
            'hasta.after_or_equal' => 'La fecha "hasta" no puede ser anterior a "desde".',
            ...Paginacion::mensajes(),
        ]);

        // Reimpresion muestra tambien los anulados (marcados como tales) para que quede el rastro del folio.
        $query = Movimiento::conAnulados()
            ->with(['villa', 'concepto', 'formaPago', 'usuario', 'anuladoPor'])
            ->orderByDesc('FECHA_APLI')
            ->orderByDesc('ID_MOV');

        if (! empty($data['villa'])) {
            $query->where('CLV_CLIE', $data['villa']);
        }
        if (! empty($data['tipo'])) {
            $query->whereHas('concepto', fn ($q) => $q->where('ES_CARGO', $data['tipo'] === 'cargo'));
        }
        if (! empty($data['q'])) {
            $query->where('FOLIO', 'like', '%'.trim($data['q']).'%');
        }
        if (! empty($data['desde'])) {
            $query->whereDate('FECHA_APLI', '>=', $data['desde']);
        }
        if (! empty($data['hasta'])) {
            $query->whereDate('FECHA_APLI', '<=', $data['hasta']);
        }

        $pagina = $query->paginate(Paginacion::porPagina($request), ['*'], 'pagina', $request->integer('pagina', 1));

        return response()->json([
            'data' => $pagina->getCollection()->map(fn (Movimiento $m) => [
                'id' => $m->ID_MOV,
                'folio' => $m->FOLIO,
                'fecha' => $m->FECHA_APLI->toDateString(),
                'villa' => $m->CLV_CLIE,
                'propietario' => $m->villa?->nombre_completo,
                'concepto' => $m->concepto->DESCR,
                'tipo' => $m->concepto->ES_CARGO ? 'cargo' : 'credito',
                'importe' => (float) $m->IMPORTE,
                'forma_pago' => $m->formaPago?->nombre,
                'usuario' => $m->usuario?->name ?? 'Sistema',
                'observacion' => $m->OBS,
                'anulado' => $m->anulado(),
                'anulado_en' => $m->ANULADO_EN?->toIso8601String(),
                'anulado_por' => $m->anulado() ? ($m->anuladoPor?->name ?? 'Sistema') : null,
                'motivo_anulacion' => $m->MOTIVO_ANULACION,
            ]),
            'meta' => [
                'pagina' => $pagina->currentPage(),
                'por_pagina' => $pagina->perPage(),
                'total' => $pagina->total(),
                'ultima_pagina' => $pagina->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'CLV_CLIE' => ['required', 'string', 'exists:villas,CLV_CLIE'],
            'NUM_CPTO' => ['required', 'integer', 'exists:conceptos,NUM_CPTO'],
            'FORMA_PAGO_ID' => [
                'nullable',
                'integer',
                'exists:formas_pago,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    $concepto = Concepto::find($request->input('NUM_CPTO'));
                    if ($concepto && ! $concepto->ES_CARGO && ! $value) {
                        $fail('Debes indicar la forma de pago del crédito/abono.');
                    }
                },
            ],
            'IMPORTE' => ['required', 'numeric', 'gt:0'],
            // La fecha del movimiento (FECHA_APLI) NO se recibe: siempre es la de hoy del servidor
            // (ver fechaDeAplicacion()). Lo unico que elige el usuario es el vencimiento.
            // Los cargos siempre llevan fecha de vencimiento (los creditos/abonos no).
            'FECHA_VENC' => [
                Rule::requiredIf(fn () => (bool) Concepto::find($request->input('NUM_CPTO'))?->ES_CARGO),
                'nullable',
                'date',
                $this->noAntesDeHoy(),
            ],
            'OBS' => ['nullable', 'string', 'max:255'],
        ], [
            'CLV_CLIE.required' => 'Debes seleccionar una villa.',
            'CLV_CLIE.exists' => 'La villa seleccionada no existe.',
            'NUM_CPTO.required' => 'Debes seleccionar un concepto.',
            'NUM_CPTO.exists' => 'El concepto seleccionado no existe.',
            'FORMA_PAGO_ID.exists' => 'La forma de pago seleccionada no existe.',
            'IMPORTE.required' => 'Debes indicar el valor del movimiento.',
            'IMPORTE.numeric' => 'El valor debe ser un número.',
            'IMPORTE.gt' => 'El valor debe ser mayor a cero.',
            'FECHA_VENC.required' => 'Debes indicar la fecha de vencimiento del cargo.',
            'FECHA_VENC.date' => 'La fecha de vencimiento no es válida.',
            'OBS.max' => 'La descripción no puede superar los 255 caracteres.',
        ]);

        $fecha = $this->fechaDeAplicacion();
        $concepto = Concepto::findOrFail($data['NUM_CPTO']);
        $villa = Villa::findOrFail($data['CLV_CLIE']);

        $this->exigirCuotaConfigurada($concepto);

        $data['IMPORTE'] = $this->montoParaConcepto($concepto, $villa, (float) $data['IMPORTE']);

        // los creditos/abonos no llevan vencimiento: solo sirve para la antiguedad de los cargos
        if (! $concepto->ES_CARGO) {
            $data['FECHA_VENC'] = null;
        }

        $movimiento = Movimiento::create([
            ...$data,
            'FECHA_APLI' => $fecha->toDateString(),
            'ANIO' => $fecha->year,
            'MES' => $fecha->month,
            'USUARIO_ID' => $request->user()->id,
            'FOLIO' => $this->folioService->siguiente($concepto->ES_CARGO),
        ]);

        $this->saldoService->recalcularSaldo($villa);

        // un pago antes de fin de mes que cubre todo lo adeudado anula sola la mora de ese mes
        $moraAnulada = $concepto->ES_CARGO ? null : $this->moraService->anularSiPagoAntesDeFinDeMes($villa, $fecha);

        return response()->json([
            'movimiento' => $movimiento->load('concepto', 'formaPago'),
            'saldo_villa' => (float) $villa->refresh()->SALDO,
            'mora_anulada' => $moraAnulada ? ['folio' => $moraAnulada->FOLIO, 'importe' => (float) $moraAnulada->IMPORTE] : null,
        ], 201);
    }

    /**
     * Aplica un mismo cargo (ej. la cuota mensual) a todas las villas que tengan
     * activado "Aplicar cuota mensual" (villas.APLICOBRO).
     */
    public function aplicarATodas(Request $request)
    {
        $data = $request->validate([
            'NUM_CPTO' => [
                'required',
                'integer',
                Rule::exists('conceptos', 'NUM_CPTO')->where('ES_CARGO', true),
            ],
            'IMPORTE' => ['required', 'numeric', 'gt:0'],
            // igual que en store(): la fecha de aplicacion la fija el servidor, solo se elige el vencimiento
            'FECHA_VENC' => ['required', 'date', $this->noAntesDeHoy()],
            'OBS' => ['nullable', 'string', 'max:255'],
        ], [
            'NUM_CPTO.required' => 'Debes seleccionar un concepto.',
            'NUM_CPTO.exists' => 'El concepto debe ser un cargo (no un abono) para aplicarlo a todas las villas.',
            'IMPORTE.required' => 'Debes indicar el valor del cargo.',
            'IMPORTE.numeric' => 'El valor debe ser un número.',
            'IMPORTE.gt' => 'El valor debe ser mayor a cero.',
            'FECHA_VENC.required' => 'Debes indicar la fecha de vencimiento del cargo.',
            'FECHA_VENC.date' => 'La fecha de vencimiento no es válida.',
            'OBS.max' => 'La descripción no puede superar los 255 caracteres.',
        ]);

        $fecha = $this->fechaDeAplicacion();
        $concepto = Concepto::findOrFail($data['NUM_CPTO']);
        $this->exigirCuotaConfigurada($concepto);
        $villas = Villa::where('APLICOBRO', true)->get();
        $villasConCuotaEspecial = 0;

        foreach ($villas as $villa) {
            // "Cuota especial" no exime a la villa del cargo masivo -- solo cambia el
            // monto que se le cobra, y unicamente en la cuota de mantenimiento
            // (ver montoParaConcepto()).
            $importe = $this->montoParaConcepto($concepto, $villa, (float) $data['IMPORTE']);

            if ($concepto->ES_MANTENIMIENTO && $villa->CUOTA_ESPECIAL && $villa->MONTO_CUOTA_ESPECIAL !== null) {
                $villasConCuotaEspecial++;
            }

            Movimiento::create([
                'CLV_CLIE' => $villa->CLV_CLIE,
                'NUM_CPTO' => $data['NUM_CPTO'],
                'IMPORTE' => $importe,
                'FECHA_APLI' => $fecha->toDateString(),
                'FECHA_VENC' => $data['FECHA_VENC'] ?? null,
                'OBS' => $data['OBS'] ?? null,
                'ANIO' => $fecha->year,
                'MES' => $fecha->month,
                'USUARIO_ID' => $request->user()->id,
                'FOLIO' => $this->folioService->siguiente(true),
            ]);

            $this->saldoService->recalcularSaldo($villa);
        }

        return response()->json([
            'villas_afectadas' => $villas->count(),
            'villas_con_cuota_especial' => $villasConCuotaEspecial,
            'concepto' => $concepto,
        ], 201);
    }

    /**
     * Anula un cargo o abono capturado por error (solo Director/Admin). No se borra: conserva su
     * folio y queda quien, cuando y por que; pero deja de contar en saldos, estados de cuenta,
     * reportes y dashboard. El movimiento correcto se vuelve a capturar normal, con folio nuevo.
     */
    public function anular(Request $request, Movimiento $movimiento)
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'motivo.required' => 'Debes indicar el motivo de la anulación.',
            'motivo.min' => 'Describe el motivo con un poco más de detalle.',
            'motivo.max' => 'El motivo no puede superar los 255 caracteres.',
        ]);

        if ($movimiento->anulado()) {
            throw ValidationException::withMessages([
                'motivo' => 'Este movimiento ya está anulado.',
            ]);
        }

        $movimiento->anular($request->user(), trim($data['motivo']));
        $movimiento->load('concepto');

        $villa = Villa::findOrFail($movimiento->CLV_CLIE);
        $this->saldoService->recalcularSaldo($villa);

        $tipo = $movimiento->concepto->ES_CARGO ? 'el cargo' : 'el abono';
        Bitacora::registrar(
            'movimiento',
            'anular',
            'Anuló '.$tipo.' folio '.($movimiento->FOLIO ?? $movimiento->ID_MOV).' ('.$movimiento->concepto->DESCR.') de la villa '
                .$movimiento->CLV_CLIE.' por '.Formato::monto((float) $movimiento->IMPORTE).'. Motivo: '.$movimiento->MOTIVO_ANULACION,
        );

        return response()->json([
            'id' => $movimiento->ID_MOV,
            'saldo_villa' => (float) $villa->SALDO,
        ]);
    }

    /**
     * Resuelve el monto real a cobrar para un concepto. El usuario nunca escribe el monto de
     * un concepto que lo tiene definido en Configuracion:
     * - Cuota de mantenimiento: la cuota mensual de Configuracion > Cuotas, salvo que la
     *   villa tenga cuota especial, en cuyo caso paga el monto asignado a esa villa.
     *   (Solo este concepto usa la cuota especial.)
     * - Otro concepto con MONTO_DEFAULT (monto fijo): ese monto para todas las villas.
     * - Concepto sin monto fijo (ej. Mora, Cargo extraordinario): monto variable, se respeta
     *   lo que el usuario escribio.
     */
    private function montoParaConcepto(Concepto $concepto, Villa $villa, float $importeSolicitado): float
    {
        if ($concepto->MONTO_DEFAULT === null) {
            return $importeSolicitado;
        }

        if ($concepto->ES_MANTENIMIENTO && $villa->CUOTA_ESPECIAL && $villa->MONTO_CUOTA_ESPECIAL !== null) {
            return (float) $villa->MONTO_CUOTA_ESPECIAL;
        }

        return (float) $concepto->MONTO_DEFAULT;
    }

    /**
     * La cuota de mantenimiento no se puede aplicar mientras Director/Admin no haya definido
     * su monto: si no, quedaria como concepto de monto libre y cualquiera podria escribirlo.
     */
    private function exigirCuotaConfigurada(Concepto $concepto): void
    {
        if ($concepto->ES_MANTENIMIENTO && $concepto->MONTO_DEFAULT === null) {
            throw ValidationException::withMessages([
                'NUM_CPTO' => 'La cuota de mantenimiento aún no está configurada. Un Director o Administrador debe definirla en Configuración → Cuotas.',
            ]);
        }
    }

    /**
     * Fecha con la que se registra todo movimiento: hoy, segun el reloj del servidor en la zona
     * horaria de la app (America/Tegucigalpa, ver config/app.php). El usuario no la puede elegir
     * ni modificar, para que ningun cargo/abono quede con una fecha equivocada (ni en el pasado,
     * que alteraria estados de cuenta ya emitidos, ni en el futuro, que no se veria en los saldos).
     */
    private function fechaDeAplicacion(): Carbon
    {
        return Carbon::today();
    }

    /**
     * Regla para la fecha de vencimiento: no puede ser anterior a hoy (fecha del servidor).
     * Si el valor no es una fecha valida no dice nada; de eso se encarga la regla `date`.
     */
    private function noAntesDeHoy(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            try {
                $fecha = Carbon::parse($value)->startOfDay();
            } catch (\Throwable) {
                return;
            }

            if ($fecha->lt($this->fechaDeAplicacion())) {
                $fail('La fecha de vencimiento no puede ser anterior a hoy.');
            }
        };
    }
}
