<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$rolesPermitidos): Response
    {
        $rol = $request->user()?->role?->nombre;

        if (! $rol || ! in_array($rol, $rolesPermitidos, true)) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }

        return $next($request);
    }
}
