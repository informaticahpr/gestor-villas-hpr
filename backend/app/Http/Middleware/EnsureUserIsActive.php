<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si al usuario lo deshabilitan mientras tiene la sesion abierta, en su siguiente peticion se le
 * cierra la sesion (401). Sin esto podria seguir usando el sistema hasta que la sesion venciera,
 * porque la cuenta solo se revisa al iniciar sesion.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->activo) {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json([
                'message' => 'Tu cuenta ha sido deshabilitada. Contacta a un administrador.',
                'cuenta_deshabilitada' => true,
            ], 401);
        }

        return $next($request);
    }
}
