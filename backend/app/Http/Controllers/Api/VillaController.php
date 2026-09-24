<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Villa;
use App\Services\SaldoService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VillaController extends Controller
{
    public function __construct(private readonly SaldoService $saldoService) {}

    public function index(Request $request)
    {
        $q = $this->sinAcentos(trim((string) $request->query('q', '')));
        $qClave = str_replace('-', '', $q);

        $villas = Villa::query()
            ->when($q !== '', function ($query) use ($q, $qClave) {
                $query->where(function ($sub) use ($q, $qClave) {
                    $sub->whereRaw($this->columnaClave('CLV_CLIE').' LIKE ?', ["%{$qClave}%"])
                        ->orWhereRaw($this->columnaSinAcentos('NOMBRES').' LIKE ?', ["%{$q}%"])
                        ->orWhereRaw($this->columnaSinAcentos('APELLIDOS').' LIKE ?', ["%{$q}%"]);
                })->limit(8);
            })
            ->when($request->boolean('cuota_especial'), fn ($query) => $query->where('CUOTA_ESPECIAL', true))
            ->orderBy('CLV_CLIE')
            ->get();

        $saldos = $this->saldoService->saldosPorVilla();

        return response()->json([
            'data' => $villas->map(fn (Villa $v) => $this->resumen($v, $saldos->get($v->CLV_CLIE, 0.0))),
        ]);
    }

    /**
     * Mapa de vocales/eñe acentuadas -> su forma simple, para que la busqueda
     * encuentre "Sanchez" aunque el nombre real este guardado como "Sánchez".
     */
    private const MAPA_ACENTOS = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
    ];

    private function sinAcentos(string $texto): string
    {
        return strtolower(strtr($texto, self::MAPA_ACENTOS));
    }

    /**
     * Expresion SQL que aplica el mismo mapa de acentos a una columna, para
     * poder comparar contra un termino de busqueda ya normalizado.
     */
    private function columnaSinAcentos(string $columna): string
    {
        $expr = "LOWER({$columna})";
        foreach (self::MAPA_ACENTOS as $con => $sin) {
            $expr = "REPLACE({$expr}, '{$con}', '{$sin}')";
        }

        return $expr;
    }

    /**
     * Igual que columnaSinAcentos(), pero ademas le quita los guiones a la
     * columna -- asi "A-1" hace match si el usuario busca "A1" sin guion.
     * Se usa solo para CLV_CLIE (codigo de villa), no para nombres.
     */
    private function columnaClave(string $columna): string
    {
        return "REPLACE(".$this->columnaSinAcentos($columna).", '-', '')";
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
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, creando: true);

        $villa = Villa::create($data + ['SALDO' => 0]);

        return response()->json(['villa' => $this->detalle($villa)], 201);
    }

    public function update(Request $request, string $villa)
    {
        $villa = Villa::findOrFail($villa);
        $data = $this->validated($request, creando: false);

        $villa->update($data);

        return response()->json(['villa' => $this->detalle($villa->refresh())]);
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
     * Solo digitos, con guiones/espacios opcionales para el formato "2234-5601".
     */
    private const REGEX_SOLO_NUMEROS = 'regex:/^[0-9][0-9\-\s]*$/';

    /**
     * Ademas de la regla 'email' de Laravel (que acepta direcciones sin dominio real,
     * ej. "user@localhost"), exige que siempre haya un dominio con extension (ej. "@x.com").
     */
    private const REGEX_CORREO_CON_DOMINIO = 'regex:/^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/';

    private function validated(Request $request, bool $creando): array
    {
        if ($request->filled('CLV_CLIE')) {
            $request->merge(['CLV_CLIE' => mb_strtoupper($request->input('CLV_CLIE'), 'UTF-8')]);
        }

        $reglas = [
            'NOMBRES' => ['required', 'string', 'max:60', self::REGEX_SOLO_LETRAS],
            'APELLIDOS' => ['required', 'string', 'max:60', self::REGEX_SOLO_LETRAS],
            'DIR' => ['required', 'string', 'max:255'],
            'TELF' => [
                'nullable', 'string', 'max:20', self::REGEX_SOLO_NUMEROS,
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if (! $value && ! $request->filled('CELULAR') && ! $request->filled('OTRO_TEL')) {
                        $fail('Debes indicar al menos un teléfono (Celular 1, Celular 2 u Otro).');
                    }
                },
            ],
            'CELULAR' => ['nullable', 'string', 'max:20', self::REGEX_SOLO_NUMEROS],
            'OTRO_TEL' => ['nullable', 'string', 'max:20', self::REGEX_SOLO_NUMEROS],
            'MAIL' => [
                'nullable', 'email', 'max:60', self::REGEX_CORREO_CON_DOMINIO,
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    if (! $value && ! $request->filled('MAIL2')) {
                        $fail('Debes indicar al menos un correo (Correo Electrónico 1 o 2).');
                    }
                },
            ],
            'MAIL2' => ['nullable', 'email', 'max:60', self::REGEX_CORREO_CON_DOMINIO],
            'FCONTRUC' => ['nullable', 'date'],
            'NOMED' => ['nullable', 'string', 'max:20'],
            'FECHA_NAC' => ['nullable', 'date'],
            'NOHAB' => ['nullable', 'integer', 'min:0'],
            'NOBATH' => ['nullable', 'integer', 'min:0'],
            'APLICOBRO' => ['boolean'],
            'CUOTA_ESPECIAL' => ['boolean'],
        ];

        if ($creando) {
            $reglas['CLV_CLIE'] = ['required', 'string', 'max:5', 'unique:CLIE1,CLV_CLIE'];
        }

        $mensajes = [
            'CLV_CLIE.required' => 'Debes indicar el número de villa.',
            'CLV_CLIE.max' => 'El número de villa no puede tener más de 5 caracteres.',
            'CLV_CLIE.unique' => 'Ya existe una villa registrada con ese número.',
            'NOMBRES.required' => 'Los nombres del propietario no pueden quedar vacíos.',
            'NOMBRES.max' => 'Los nombres no pueden superar los 60 caracteres.',
            'NOMBRES.regex' => 'El nombre solo puede contener letras.',
            'APELLIDOS.required' => 'Los apellidos del propietario no pueden quedar vacíos.',
            'APELLIDOS.max' => 'Los apellidos no pueden superar los 60 caracteres.',
            'APELLIDOS.regex' => 'El apellido solo puede contener letras.',
            'DIR.required' => 'Debes indicar el bloque de la villa.',
            'DIR.max' => 'El bloque no puede superar los 255 caracteres.',
            'TELF.regex' => 'El teléfono solo puede contener números.',
            'CELULAR.regex' => 'El celular solo puede contener números.',
            'OTRO_TEL.regex' => 'El teléfono solo puede contener números.',
            'MAIL.email' => 'El correo electrónico no tiene un formato válido.',
            'MAIL.regex' => 'El correo debe tener un dominio válido, ej. nombre@dominio.com.',
            'MAIL2.email' => 'El correo electrónico no tiene un formato válido.',
            'MAIL2.regex' => 'El correo debe tener un dominio válido, ej. nombre@dominio.com.',
            'FCONTRUC.date' => 'La fecha de entrega no es válida.',
            'NOMED.max' => 'La clave ENEE no puede superar los 20 caracteres.',
            'FECHA_NAC.date' => 'La fecha de nacimiento no es válida.',
            'NOHAB.integer' => 'El número de habitaciones debe ser un número entero.',
            'NOHAB.min' => 'El número de habitaciones no puede ser negativo.',
            'NOBATH.integer' => 'El número de baños debe ser un número entero.',
            'NOBATH.min' => 'El número de baños no puede ser negativo.',
        ];

        $data = $request->validate($reglas, $mensajes);

        $data['NOMBRES'] = mb_strtoupper($data['NOMBRES'], 'UTF-8');
        $data['APELLIDOS'] = mb_strtoupper($data['APELLIDOS'], 'UTF-8');

        return $data;
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
        return [
            'CLV_CLIE' => $villa->CLV_CLIE,
            'NOMBRES' => $villa->NOMBRES,
            'APELLIDOS' => $villa->APELLIDOS,
            'DIR' => $villa->DIR,
            'TELF' => $villa->TELF,
            'CELULAR' => $villa->CELULAR,
            'OTRO_TEL' => $villa->OTRO_TEL,
            'MAIL' => $villa->MAIL,
            'MAIL2' => $villa->MAIL2,
            'FCONTRUC' => $villa->FCONTRUC?->toDateString(),
            'NOMED' => $villa->NOMED,
            'FECHA_NAC' => $villa->FECHA_NAC?->toDateString(),
            'NOHAB' => $villa->NOHAB,
            'NOBATH' => $villa->NOBATH,
            'APLICOBRO' => (bool) $villa->APLICOBRO,
            'CUOTA_ESPECIAL' => (bool) $villa->CUOTA_ESPECIAL,
            'MONTO_CUOTA_ESPECIAL' => $villa->MONTO_CUOTA_ESPECIAL !== null ? (float) $villa->MONTO_CUOTA_ESPECIAL : null,
            'SALDO' => $this->saldoService->saldoDeVilla($villa),
        ];
    }
}
