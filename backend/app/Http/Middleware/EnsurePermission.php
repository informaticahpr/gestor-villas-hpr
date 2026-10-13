<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `permiso:a,b`: deja pasar si el cargo del usuario tiene al menos uno (ver Configuracion -> Permisos). */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        $user = $request->user();

        foreach ($permisos as $permiso) {
            if ($user?->tienePermiso($permiso)) {
                return $next($request);
            }
        }

        abort(403, 'No tienes permiso para realizar esta accion.');
    }
}
