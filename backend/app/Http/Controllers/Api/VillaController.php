<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuscaSinAcentos;
use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Encargado;
use App\Models\Propietario;
use App\Models\Role;
use App\Models\Villa;
use App\Models\VillaHistorial;
use App\Services\SaldoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VillaController extends Controller
{
    use BuscaSinAcentos;

    public function __construct(private readonly SaldoService $saldoService) {}

    public function index(Request $request)
    {
        $q = $this->sinAcentos(trim((string) $request->query('q', '')));
        $qClave = str_replace('-', '', $q);

        $villas = Villa::query()
            ->when($q !== '', function ($query) use ($q, $qClave) {
                $query->where(function ($sub) use ($q, $qClave) {
                    $sub->whereRaw($this->columnaClave('CLV_CLIE').' LIKE ?', ["%{$qClave}%"])
                        ->orWhereHas('propietario', fn ($p) => $p
                            ->whereRaw($this->columnaSinAcentos('NOMBRES').' LIKE ?', ["%{$q}%"])
                            ->orWhereRaw($this->columnaSinAcentos('APELLIDOS').' LIKE ?', ["%{$q}%"]));
                });
            })
            ->when($request->boolean('cuota_especial'), fn ($query) => $query->where('CUOTA_ESPECIAL', true))
            ->get();

        // orden natural (A-1, A-2 ... A-10) y, al buscar, solo las primeras 8 ya ordenadas
        $villas = Villa::enOrdenNatural($villas);
        if ($q !== '') {
            $villas = $villas->take(8);
        }

        $saldos = $this->saldoService->saldosPorVilla();

        return response()->json([
            'data' => $villas->map(fn (Villa $v) => $this->resumen($v, $saldos->get($v->CLV_CLIE, 0.0))),
        ]);
    }

    public function show(string $villa)
    {
        $villa = Villa::findOrFail($villa);

        // Ultimo año exacto: de hoy (fecha del servidor, zona horaria de la app) a la misma fecha del año anterior.
        $hasta = Carbon::today();
        $desde = $hasta->copy()->subYearNoOverflow();

        return response()->json([
            'villa' => $this->detalle($villa),
            'estado_cuenta' => $this->saldoService->estadoDeCuenta($villa, $desde, $hasta),
            'periodo' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],
            'historial' => $this->historial($villa),
        ]);
    }

    /**
     * Pestaña "Historial de Villa": desde cuando estan el propietario y el encargado actuales, y los
     * anteriores (del cambio mas reciente al mas antiguo) con sus datos tal como estaban registrados.
     */
    private function historial(Villa $villa): array
    {
        $anteriores = $villa->historial()->with('usuario')->orderByDesc('HASTA')->orderByDesc('id')->get()
            ->map(fn (VillaHistorial $h) => [
                'id' => $h->id,
                'tipo' => $h->TIPO,
                'nombre_completo' => $h->nombre_completo,
                ...$h->only(['NOMBRES', 'APELLIDOS', 'DNI', 'PARENTESCO', 'TELF', 'CELULAR', 'OTRO_TEL', 'MAIL', 'MAIL2']),
                'FECHA_NAC' => $h->FECHA_NAC?->toDateString(),
                'desde' => $h->DESDE?->toDateString(),
                'hasta' => $h->HASTA->toDateString(),
                'registrado_por' => $h->usuario?->name,
            ]);

        return [
            'propietario_desde' => $this->propietarioDesde($villa)?->toDateString(),
            'encargado_desde' => $villa->encargado?->created_at?->toDateString(),
            'propietarios' => $anteriores->where('tipo', VillaHistorial::PROPIETARIO)->values(),
            'encargados' => $anteriores->where('tipo', VillaHistorial::ENCARGADO)->values(),
        ];
    }

    /**
     * Crea la villa con su propietario (uno nuevo, o uno ya registrado si llega propietario.id) y,
     * si se llenó algún dato, su encargado. Cualquier usuario puede crear villas; solo Director/Admin
     * pueden modificar los datos de un propietario ya registrado (el Supervisor solo lo vincula).
     */
    public function store(Request $request)
    {
        $data = $this->validated($request, creando: true);
        $puedeEditarPropietario = in_array($request->user()->role?->nombre, [Role::DIRECTOR, Role::ADMIN], true);

        $villa = DB::transaction(function () use ($data, $puedeEditarPropietario) {
            $propietario = $this->guardarPropietario($data['propietario'], $puedeEditarPropietario);

            $villa = Villa::create($data['villa'] + ['PROPIETARIO_ID' => $propietario->id, 'SALDO' => 0]);
            $this->guardarEncargado($villa, $data['encargado']);

            return $villa;
        });

        return response()->json(['villa' => $this->detalle($villa->refresh())], 201);
    }

    /**
     * Solo Director/Admin (ver routes/api.php). Si la villa cambia de propietario o de encargado, el
     * que sale queda guardado en el historial de la villa con sus datos tal como estaban.
     */
    public function update(Request $request, string $villa)
    {
        $villa = Villa::with(['propietario', 'encargado'])->findOrFail($villa);
        $data = $this->validated($request, creando: false);
        $usuarioId = $request->user()->id;

        DB::transaction(function () use ($villa, $data, $usuarioId) {
            // "fotos" de quienes estaban antes del cambio (antes de guardar, por si se editan sus datos)
            $propietarioAntes = $villa->propietario;
            $datosPropietarioAntes = $propietarioAntes?->only(Encargado::CAMPOS);
            $encargadoAntes = $villa->encargado;

            $propietario = $this->guardarPropietario($data['propietario'], puedeEditarExistente: true);
            $villa->update($data['villa'] + ['PROPIETARIO_ID' => $propietario->id]);

            if ($propietarioAntes && $propietarioAntes->id !== $propietario->id) {
                $this->registrarEnHistorial($villa, VillaHistorial::PROPIETARIO, $datosPropietarioAntes, $usuarioId,
                    desde: $this->propietarioDesde($villa), propietarioId: $propietarioAntes->id);
            }

            $encargadoNuevo = array_intersect_key($data['encargado'], array_flip(Encargado::CAMPOS));
            $hayEncargadoNuevo = collect($encargadoNuevo)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

            if ($encargadoAntes && (! $hayEncargadoNuevo || $this->esOtraPersona($encargadoAntes->only(Encargado::CAMPOS), $encargadoNuevo))) {
                $this->registrarEnHistorial($villa, VillaHistorial::ENCARGADO, $encargadoAntes->only(Encargado::CAMPOS), $usuarioId,
                    desde: $encargadoAntes->created_at);
                // se borra para que el nuevo encargado tenga su propia fecha de inicio (created_at)
                $encargadoAntes->delete();
            }

            $this->guardarEncargado($villa, $data['encargado']);
        });

        return response()->json(['villa' => $this->detalle($villa->refresh())]);
    }

    private function registrarEnHistorial(Villa $villa, string $tipo, array $datos, int $usuarioId, $desde, ?int $propietarioId = null): void
    {
        VillaHistorial::create($datos + [
            'CLV_CLIE' => $villa->CLV_CLIE,
            'TIPO' => $tipo,
            'PROPIETARIO_ID' => $propietarioId,
            'DESDE' => $desde ? Carbon::parse($desde)->toDateString() : null,
            'HASTA' => Carbon::today()->toDateString(),
            'USUARIO_ID' => $usuarioId,
        ]);
    }

    /** Desde cuando el propietario actual lo es: el ultimo cambio registrado o, si no hay, el alta de la villa. */
    private function propietarioDesde(Villa $villa): ?Carbon
    {
        $ultimoCambio = $villa->historial()->where('TIPO', VillaHistorial::PROPIETARIO)->max('HASTA');

        return $ultimoCambio ? Carbon::parse($ultimoCambio) : $villa->created_at;
    }

    /**
     * Distinto encargado = otro nombre u otro DNI. Cambiarle solo el celular o el correo a la misma
     * persona no cuenta como cambio de encargado.
     */
    private function esOtraPersona(array $antes, array $nuevo): bool
    {
        $nombre = fn (array $p) => mb_strtoupper(trim(($p['NOMBRES'] ?? '').' '.($p['APELLIDOS'] ?? '')), 'UTF-8');
        $dniAntes = trim((string) ($antes['DNI'] ?? ''));
        $dniNuevo = trim((string) ($nuevo['DNI'] ?? ''));

        return $nombre($antes) !== $nombre($nuevo) || ($dniAntes !== '' && $dniNuevo !== '' && $dniAntes !== $dniNuevo);
    }

    /**
     * Con id: usa ese propietario (y actualiza sus datos si se permite -- el cambio aplica a todas
     * sus villas). Sin id: crea uno nuevo.
     */
    private function guardarPropietario(array $datos, bool $puedeEditarExistente): Propietario
    {
        $id = $datos['id'] ?? null;
        unset($datos['id']);

        if (! $id) {
            return Propietario::create($datos);
        }

        $propietario = Propietario::findOrFail($id);
        if ($puedeEditarExistente) {
            $propietario->update($datos);
        }

        return $propietario;
    }

    /** El encargado es opcional: si todos sus campos llegan vacíos, se elimina (o no se crea). */
    private function guardarEncargado(Villa $villa, array $datos): void
    {
        $datos = array_intersect_key($datos, array_flip(Encargado::CAMPOS));

        if (collect($datos)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
            $villa->encargado()->delete();

            return;
        }

        Encargado::updateOrCreate(['CLV_CLIE' => $villa->CLV_CLIE], $datos);
    }

    /**
     * Actualiza el monto y/o el estado activo de la cuota especial de una villa --
     * pantalla dedicada en Configuracion, no pasa por el formulario completo de
     * edicion de villa. Tambien se usa para "desactivar" (CUOTA_ESPECIAL=false,
     * conserva el monto) y "eliminar" (CUOTA_ESPECIAL=false + monto en null).
     */
    public function actualizarCuotaEspecial(Request $request, string $villa)
    {
        $villa = Villa::findOrFail($villa);

        $data = $request->validate([
            'MONTO_CUOTA_ESPECIAL' => ['nullable', 'numeric', 'min:0'],
            'CUOTA_ESPECIAL' => ['sometimes', 'boolean'],
        ], [
            'MONTO_CUOTA_ESPECIAL.numeric' => 'El monto debe ser un número.',
            'MONTO_CUOTA_ESPECIAL.min' => 'El monto no puede ser negativo.',
        ]);

        // Detecta la intencion segun lo que llego, para dejar un mensaje claro en
        // la bitacora: desactivar (se apaga el check, se conserva el monto),
        // eliminar (se apaga el check y se borra el monto), o solo editar el monto.
        if (array_key_exists('CUOTA_ESPECIAL', $data) && ! $data['CUOTA_ESPECIAL']) {
            $accion = array_key_exists('MONTO_CUOTA_ESPECIAL', $data) && $data['MONTO_CUOTA_ESPECIAL'] === null
                ? 'eliminar'
                : 'desactivar';
        } else {
            $accion = 'editar';
        }

        $villa->update($data);

        $descripciones = [
            'eliminar' => "Eliminó la cuota especial de la villa {$villa->CLV_CLIE}.",
            'desactivar' => "Desactivó la cuota especial de la villa {$villa->CLV_CLIE}.",
            'editar' => "Actualizó la cuota especial de la villa {$villa->CLV_CLIE}.",
        ];
        Bitacora::registrar('cuota_especial', $accion, $descripciones[$accion]);

        return response()->json(['villa' => $this->detalle($villa->refresh())]);
    }

    /**
     * Solo letras (con acentos/eñe) y espacios -- sin digitos ni simbolos.
     */
    private const REGEX_SOLO_LETRAS = 'regex:/^[\pL\s]+$/u';

    /**
     * Solo digitos, con guiones/espacios opcionales para el formato "9897-2123".
     */
    private const REGEX_SOLO_NUMEROS = 'regex:/^[0-9][0-9\-\s]*$/';

    /**
     * Ademas de la regla 'email' de Laravel (que acepta direcciones sin dominio real,
     * ej. "user@localhost"), exige que siempre haya un dominio con extension (ej. "@x.com").
     */
    private const REGEX_CORREO_CON_DOMINIO = 'regex:/^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/';

    /**
     * Valida el formulario de villa, que llega como
     * { CLV_CLIE, propietario: {id?, ...}, encargado: {...}, villa: {...} }
     * y se devuelve con esas tres secciones listas para guardar.
     */
    private function validated(Request $request, bool $creando): array
    {
        if ($request->filled('CLV_CLIE')) {
            $request->merge(['CLV_CLIE' => mb_strtoupper($request->input('CLV_CLIE'), 'UTF-8')]);
        }

        $propietarioId = $request->input('propietario.id');

        $reglas = [
            // --- Datos de Propietario ---
            'propietario' => ['required', 'array'],
            'propietario.id' => ['nullable', 'integer', 'exists:propietarios,id'],
            'propietario.NOMBRES' => ['required', 'string', 'max:60', self::REGEX_SOLO_LETRAS],
            'propietario.APELLIDOS' => ['required', 'string', 'max:60', self::REGEX_SOLO_LETRAS],
            'propietario.DNI' => ['nullable', 'string', 'max:30', Rule::unique('propietarios', 'DNI')->ignore($propietarioId)],
            // al menos un telefono y al menos un correo (required_without* si se evalua con el campo vacio,
            // a diferencia de una regla personalizada, que Laravel salta cuando el campo viene vacio)
            'propietario.TELF' => ['nullable', 'required_without_all:propietario.CELULAR,propietario.OTRO_TEL', 'string', 'max:20', self::REGEX_SOLO_NUMEROS],
            'propietario.CELULAR' => ['nullable', 'string', 'max:20', self::REGEX_SOLO_NUMEROS],
            'propietario.OTRO_TEL' => ['nullable', 'string', 'max:20', self::REGEX_SOLO_NUMEROS],
            'propietario.MAIL' => ['nullable', 'required_without:propietario.MAIL2', 'email', 'max:60', self::REGEX_CORREO_CON_DOMINIO],
            'propietario.MAIL2' => ['nullable', 'email', 'max:60', self::REGEX_CORREO_CON_DOMINIO],
            'propietario.FECHA_NAC' => ['nullable', 'date'],

            // --- Datos de Encargado (todo opcional) ---
            'encargado' => ['nullable', 'array'],
            'encargado.NOMBRES' => ['nullable', 'string', 'max:60', self::REGEX_SOLO_LETRAS],
            'encargado.APELLIDOS' => ['nullable', 'string', 'max:60', self::REGEX_SOLO_LETRAS],
            'encargado.DNI' => ['nullable', 'string', 'max:30'],
            'encargado.TELF' => ['nullable', 'string', 'max:20', self::REGEX_SOLO_NUMEROS],
            'encargado.CELULAR' => ['nullable', 'string', 'max:20', self::REGEX_SOLO_NUMEROS],
            'encargado.OTRO_TEL' => ['nullable', 'string', 'max:20', self::REGEX_SOLO_NUMEROS],
            'encargado.MAIL' => ['nullable', 'email', 'max:60', self::REGEX_CORREO_CON_DOMINIO],
            'encargado.MAIL2' => ['nullable', 'email', 'max:60', self::REGEX_CORREO_CON_DOMINIO],
            'encargado.FECHA_NAC' => ['nullable', 'date'],
            'encargado.PARENTESCO' => ['nullable', 'string', 'max:60'],

            // --- Datos de Villa ---
            'villa' => ['required', 'array'],
            'villa.DIR' => ['required', 'string', 'max:255'],
            'villa.FCONTRUC' => ['required', 'date'],
            'villa.NOMED' => ['nullable', 'string', 'max:20'],
            'villa.CLAVE_CATASTRAL' => ['nullable', 'string', 'max:40'],
            'villa.DESCRIPCION_IP' => ['nullable', 'string', 'max:255'],
            'villa.OBSERVACION' => ['nullable', 'string', 'max:500'],
            'villa.NOHAB' => ['nullable', 'integer', 'min:0'],
            'villa.NOBATH' => ['nullable', 'integer', 'min:0'],
            'villa.APLICOBRO' => ['boolean'],
            'villa.CUOTA_ESPECIAL' => ['boolean'],
        ];

        if ($creando) {
            $reglas['CLV_CLIE'] = ['required', 'string', 'max:5', 'unique:villas,CLV_CLIE'];
        }

        $mensajes = [
            'CLV_CLIE.required' => 'Debes indicar el número de villa.',
            'CLV_CLIE.max' => 'El número de villa no puede tener más de 5 caracteres.',
            'CLV_CLIE.unique' => 'Ya existe una villa registrada con ese número.',
            'propietario.id.exists' => 'El propietario seleccionado ya no existe.',
            'propietario.NOMBRES.required' => 'Los nombres del propietario no pueden quedar vacíos.',
            'propietario.APELLIDOS.required' => 'Los apellidos del propietario no pueden quedar vacíos.',
            'propietario.TELF.required_without_all' => 'Debes indicar al menos un teléfono del propietario (Celular 1, Celular 2 u Otro).',
            'propietario.MAIL.required_without' => 'Debes indicar al menos un correo del propietario (Correo Electrónico 1 o 2).',
            'propietario.DNI.unique' => 'Ya hay otro propietario con ese DNI/Pasaporte. Búscalo en "Propietario ya registrado".',
            'encargado.PARENTESCO.max' => 'El parentesco/vínculo del encargado no puede superar los 60 caracteres.',
            'villa.DIR.required' => 'Debes indicar la ubicación de la villa.',
            'villa.DIR.max' => 'La ubicación no puede superar los 255 caracteres.',
            'villa.FCONTRUC.required' => 'Debes indicar la fecha de entrega de la villa.',
            'villa.FCONTRUC.date' => 'La fecha de entrega no es válida.',
            'villa.NOMED.max' => 'El medidor ENEE no puede superar los 20 caracteres.',
            'villa.CLAVE_CATASTRAL.max' => 'La clave catastral no puede superar los 40 caracteres.',
            'villa.DESCRIPCION_IP.max' => 'La descripción IP no puede superar los 255 caracteres.',
            'villa.OBSERVACION.max' => 'La observación de la villa no puede superar los 500 caracteres.',
            'villa.NOHAB.integer' => 'El número de habitaciones debe ser un número entero.',
            'villa.NOHAB.min' => 'El número de habitaciones no puede ser negativo.',
            'villa.NOBATH.integer' => 'El número de baños debe ser un número entero.',
            'villa.NOBATH.min' => 'El número de baños no puede ser negativo.',
        ];

        // mismos mensajes para propietario y encargado
        foreach (['propietario' => 'del propietario', 'encargado' => 'del encargado'] as $p => $de) {
            $mensajes += [
                "$p.NOMBRES.max" => "Los nombres $de no pueden superar los 60 caracteres.",
                "$p.NOMBRES.regex" => "El nombre $de solo puede contener letras.",
                "$p.APELLIDOS.max" => "Los apellidos $de no pueden superar los 60 caracteres.",
                "$p.APELLIDOS.regex" => "El apellido $de solo puede contener letras.",
                "$p.DNI.max" => "El DNI/Pasaporte $de no puede superar los 30 caracteres.",
                "$p.TELF.regex" => "El celular 1 $de solo puede contener números.",
                "$p.CELULAR.regex" => "El celular 2 $de solo puede contener números.",
                "$p.OTRO_TEL.regex" => "El teléfono $de solo puede contener números.",
                "$p.MAIL.email" => "El correo $de no tiene un formato válido.",
                "$p.MAIL.regex" => 'El correo debe tener un dominio válido, ej. nombre@dominio.com.',
                "$p.MAIL2.email" => "El correo $de no tiene un formato válido.",
                "$p.MAIL2.regex" => 'El correo debe tener un dominio válido, ej. nombre@dominio.com.',
                "$p.FECHA_NAC.date" => "La fecha de nacimiento $de no es válida.",
            ];
        }

        $data = $request->validate($reglas, $mensajes);

        // Todo el texto se guarda en mayusculas, salvo los correos (en minusculas: asi se escriben y
        // se comparan siempre igual).
        $mayus = fn (?string $v) => $v === null ? null : (mb_strtoupper(trim($v), 'UTF-8') ?: null);
        $minus = fn (?string $v) => $v === null ? null : (mb_strtolower(trim($v), 'UTF-8') ?: null);

        $normalizarPersona = function (array $p) use ($mayus, $minus): array {
            foreach (['NOMBRES', 'APELLIDOS', 'DNI', 'PARENTESCO'] as $c) {
                if (array_key_exists($c, $p)) {
                    $p[$c] = $mayus($p[$c]);
                }
            }
            foreach (['MAIL', 'MAIL2'] as $c) {
                if (array_key_exists($c, $p)) {
                    $p[$c] = $minus($p[$c]);
                }
            }

            return $p;
        };

        $propietario = $normalizarPersona($data['propietario']);
        $encargado = $normalizarPersona($data['encargado'] ?? []);

        $villa = $data['villa'];
        foreach (['DIR', 'NOMED', 'CLAVE_CATASTRAL', 'DESCRIPCION_IP', 'OBSERVACION'] as $c) {
            if (array_key_exists($c, $villa)) {
                $villa[$c] = $mayus($villa[$c]);
            }
        }
        if ($creando) {
            $villa['CLV_CLIE'] = $data['CLV_CLIE'];
        }

        return ['villa' => $villa, 'propietario' => $propietario, 'encargado' => $encargado];
    }

    private function resumen(Villa $villa, float $saldo): array
    {
        return [
            'villa' => $villa->CLV_CLIE,
            'nombre_completo' => $villa->nombre_completo,
            'saldo' => $saldo,
            'aplicobro' => (bool) $villa->APLICOBRO,
            'cuota_especial' => (bool) $villa->CUOTA_ESPECIAL,
            'monto_cuota_especial' => $villa->MONTO_CUOTA_ESPECIAL !== null ? (float) $villa->MONTO_CUOTA_ESPECIAL : null,
        ];
    }

    private function detalle(Villa $villa): array
    {
        $villa->loadMissing(['propietario.villas', 'encargado']);
        $e = $villa->encargado;

        return [
            'CLV_CLIE' => $villa->CLV_CLIE,
            'villa' => [
                'DIR' => $villa->DIR,
                'FCONTRUC' => $villa->FCONTRUC?->toDateString(),
                'NOMED' => $villa->NOMED,
                'CLAVE_CATASTRAL' => $villa->CLAVE_CATASTRAL,
                'DESCRIPCION_IP' => $villa->DESCRIPCION_IP,
                'OBSERVACION' => $villa->OBSERVACION,
                'NOHAB' => $villa->NOHAB,
                'NOBATH' => $villa->NOBATH,
                'APLICOBRO' => (bool) $villa->APLICOBRO,
                'CUOTA_ESPECIAL' => (bool) $villa->CUOTA_ESPECIAL,
                'MONTO_CUOTA_ESPECIAL' => $villa->MONTO_CUOTA_ESPECIAL !== null ? (float) $villa->MONTO_CUOTA_ESPECIAL : null,
            ],
            'propietario' => $villa->propietario ? PropietarioController::serializar($villa->propietario) : null,
            'encargado' => $e
                ? ['FECHA_NAC' => $e->FECHA_NAC?->toDateString()] + $e->only(Encargado::CAMPOS)
                : null,
            'SALDO' => $this->saldoService->saldoDeVilla($villa),
        ];
    }
}
