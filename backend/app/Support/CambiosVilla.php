<?php

namespace App\Support;

use App\Models\Encargado;
use App\Models\Villa;
use Carbon\CarbonInterface;

/** Para la bitacora: que parte de la villa se edito (datos de villa, de propietario o de encargado). */
class CambiosVilla
{
    private const CAMPOS_VILLA = [
        'DIR', 'FCONTRUC', 'NOMED', 'CLAVE_CATASTRAL', 'DESCRIPCION_IP', 'OBSERVACION', 'NOHAB', 'NOBATH',
        'APLICOBRO', 'CUOTA_ESPECIAL',
    ];

    private const CAMPOS_PROPIETARIO = ['NOMBRES', 'APELLIDOS', 'DNI', 'TELF', 'CELULAR', 'OTRO_TEL', 'MAIL', 'MAIL2', 'FECHA_NAC'];

    /** Los datos de la villa tal como estan ahora (con su propietario y encargado cargados). */
    public static function foto(Villa $villa): array
    {
        $datos = fn ($modelo, array $campos) => $modelo
            ? collect($campos)->mapWithKeys(fn ($c) => [$c => self::valor($modelo->{$c})])->all()
            : null;

        return [
            'villa' => $datos($villa, self::CAMPOS_VILLA),
            // otro propietario (otro id) tambien cuenta como cambio
            'propietario' => ['id' => $villa->propietario?->id] + ($datos($villa->propietario, self::CAMPOS_PROPIETARIO) ?? []),
            'encargado' => $datos($villa->encargado, Encargado::CAMPOS),
        ];
    }

    /** @return list<string> las partes que cambiaron: 'villa', 'propietario' y/o 'encargado' */
    public static function partesEditadas(array $antes, array $despues): array
    {
        return array_keys(array_filter(['villa' => 0, 'propietario' => 0, 'encargado' => 0], fn ($_, $parte) => $antes[$parte] !== $despues[$parte], ARRAY_FILTER_USE_BOTH));
    }

    /** '' y null son lo mismo; las fechas se comparan por dia. */
    private static function valor(mixed $v): mixed
    {
        if ($v instanceof CarbonInterface) {
            return $v->toDateString();
        }
        if (is_string($v)) {
            $v = trim($v);

            return $v === '' ? null : $v;
        }

        return $v;
    }
}
