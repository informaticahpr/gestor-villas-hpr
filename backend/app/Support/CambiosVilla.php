<?php

namespace App\Support;

use App\Models\Encargado;
use App\Models\Villa;
use Carbon\CarbonInterface;

/**
 * Para la bitacora: que cambio al editar una villa (datos de la villa, del propietario y del
 * encargado), con el valor anterior y el nuevo, con los mismos nombres que usa el formulario.
 */
class CambiosVilla
{
    private const CAMPOS_VILLA = [
        'DIR' => 'Ubicación',
        'FCONTRUC' => 'Fecha de entrega',
        'NOMED' => 'Medidor ENEE',
        'CLAVE_CATASTRAL' => 'Clave catastral',
        'DESCRIPCION_IP' => 'Descripción IP',
        'OBSERVACION' => 'Observación',
        'NOHAB' => '# Habitaciones',
        'NOBATH' => '# Baños',
        'APLICOBRO' => 'Aplicar cuota mensual',
        'CUOTA_ESPECIAL' => 'Cuota especial',
    ];

    private const CAMPOS_PERSONA = [
        'NOMBRES' => 'Nombres',
        'APELLIDOS' => 'Apellidos',
        'DNI' => 'DNI / Pasaporte',
        'PARENTESCO' => 'Parentesco',
        'FECHA_NAC' => 'Fecha de nacimiento',
        'TELF' => 'Celular 1',
        'CELULAR' => 'Celular 2',
        'OTRO_TEL' => 'Otro teléfono',
        'MAIL' => 'Correo 1',
        'MAIL2' => 'Correo 2',
    ];

    /** Un valor largo (ej. la observacion) se corta para que la bitacora siga siendo legible. */
    private const MAX_VALOR = 80;

    /** Los datos de la villa tal como estan ahora (con su propietario y encargado cargados). */
    public static function foto(Villa $villa): array
    {
        $persona = fn ($p, array $campos) => $p ? collect($campos)->mapWithKeys(fn ($c) => [$c => self::valor($p->{$c})])->all() : null;

        return [
            'villa' => collect(array_keys(self::CAMPOS_VILLA))->mapWithKeys(fn ($c) => [$c => self::valor($villa->{$c})])->all(),
            'propietario_id' => $villa->propietario?->id,
            'propietario' => $persona($villa->propietario, array_diff(array_keys(self::CAMPOS_PERSONA), ['PARENTESCO'])),
            'encargado' => $persona($villa->encargado, Encargado::CAMPOS),
        ];
    }

    /** @return list<string> ej. ["Ubicación: 'Bloque Z' → 'Bloque Z, frente a la piscina'", ...] */
    public static function describir(array $antes, array $despues): array
    {
        $cambios = self::camposCambiados($antes['villa'], $despues['villa'], self::CAMPOS_VILLA);

        if ($antes['propietario_id'] !== $despues['propietario_id']) {
            $cambios[] = 'Propietario: '.self::nombre($antes['propietario']).' → '.self::nombre($despues['propietario']);
        } else {
            $cambios = [...$cambios, ...self::camposCambiados($antes['propietario'] ?? [], $despues['propietario'] ?? [], self::CAMPOS_PERSONA, 'Propietario · ')];
        }

        $encAntes = $antes['encargado'];
        $encDespues = $despues['encargado'];
        if (! $encAntes || ! $encDespues || self::nombre($encAntes) !== self::nombre($encDespues)) {
            if ($encAntes !== $encDespues) {
                $cambios[] = 'Encargado: '.self::nombre($encAntes).' → '.self::nombre($encDespues);
            }
        } else {
            $cambios = [...$cambios, ...self::camposCambiados($encAntes, $encDespues, self::CAMPOS_PERSONA, 'Encargado · ')];
        }

        return $cambios;
    }

    private static function camposCambiados(array $antes, array $despues, array $etiquetas, string $prefijo = ''): array
    {
        $cambios = [];
        foreach ($etiquetas as $campo => $etiqueta) {
            if (! array_key_exists($campo, $antes) && ! array_key_exists($campo, $despues)) {
                continue;
            }
            $a = $antes[$campo] ?? null;
            $d = $despues[$campo] ?? null;
            if ($a !== $d) {
                $cambios[] = "{$prefijo}{$etiqueta}: ".self::mostrar($a).' → '.self::mostrar($d);
            }
        }

        return $cambios;
    }

    /** Normaliza para comparar: '' y null son lo mismo; fechas como d/m/Y; si/no para las casillas. */
    private static function valor(mixed $v): ?string
    {
        if ($v instanceof CarbonInterface) {
            return $v->format('d/m/Y');
        }
        if (is_bool($v)) {
            return $v ? 'Sí' : 'No';
        }
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    private static function mostrar(?string $v): string
    {
        if ($v === null) {
            return '(vacío)';
        }

        return "'".(mb_strlen($v) > self::MAX_VALOR ? mb_substr($v, 0, self::MAX_VALOR).'…' : $v)."'";
    }

    private static function nombre(?array $persona): string
    {
        if (! $persona) {
            return '(ninguno)';
        }

        return "'".trim(($persona['NOMBRES'] ?? '').' '.($persona['APELLIDOS'] ?? ''))."'";
    }
}
