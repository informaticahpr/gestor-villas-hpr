<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Tamaños de pagina que ofrecen las pantallas con listados paginados (bitacora, reimpresion). */
class Paginacion
{
    /** El primero es el valor por defecto. */
    public const TAMANOS = [10, 20, 50, 100];

    /**
     * Reglas de validacion de `por_pagina` y `pagina`.
     *
     * @return array<string, list<string>>
     */
    public static function reglas(): array
    {
        return [
            'por_pagina' => ['nullable', 'integer', 'in:'.implode(',', self::TAMANOS)],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public static function mensajes(): array
    {
        return ['por_pagina.in' => 'El tamaño de página debe ser '.implode(', ', self::TAMANOS).'.'];
    }

    /** Tamaño de pagina pedido (ya validado) o el de por defecto. */
    public static function porPagina(Request $request): int
    {
        return (int) ($request->input('por_pagina') ?? self::TAMANOS[0]);
    }
}
