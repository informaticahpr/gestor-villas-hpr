<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\BuscaSinAcentos;
use App\Http\Controllers\Controller;
use App\Models\Propietario;
use Illuminate\Http\Request;

/**
 * Buscador de propietarios ya registrados, para asignarle a una villa nueva el dueño de otra
 * (un propietario puede tener varias villas). Sus datos se crean/editan desde el formulario de villa.
 */
class PropietarioController extends Controller
{
    use BuscaSinAcentos;

    public function index(Request $request)
    {
        $q = $this->sinAcentos(trim((string) $request->query('q', '')));
        $qClave = str_replace('-', '', $q);

        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $propietarios = Propietario::with('villas')
            ->where(function ($sub) use ($q, $qClave) {
                $sub->whereRaw($this->columnaSinAcentos('NOMBRES').' LIKE ?', ["%{$q}%"])
                    ->orWhereRaw($this->columnaSinAcentos('APELLIDOS').' LIKE ?', ["%{$q}%"])
                    ->orWhereRaw($this->columnaClave('DNI').' LIKE ?', ["%{$qClave}%"]);
            })
            ->orderBy('NOMBRES')
            ->orderBy('APELLIDOS')
            ->limit(8)
            ->get();

        return response()->json(['data' => $propietarios->map(fn (Propietario $p) => self::serializar($p))]);
    }

    public static function serializar(Propietario $p): array
    {
        return [
            'id' => $p->id,
            'NOMBRES' => $p->NOMBRES,
            'APELLIDOS' => $p->APELLIDOS,
            'DNI' => $p->DNI,
            'TELF' => $p->TELF,
            'CELULAR' => $p->CELULAR,
            'OTRO_TEL' => $p->OTRO_TEL,
            'MAIL' => $p->MAIL,
            'MAIL2' => $p->MAIL2,
            'FECHA_NAC' => $p->FECHA_NAC?->toDateString(),
            'nombre_completo' => $p->nombre_completo,
            'villas' => $p->villas->pluck('CLV_CLIE')->sort()->values(),
        ];
    }
}
