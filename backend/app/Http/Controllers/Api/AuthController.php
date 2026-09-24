<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Hash bcrypt valido de una contraseña que nadie usa (no corresponde a ningun usuario real).
     * Sirve solo para que Hash::check() tarde lo mismo cuando el usuario no existe, y asi el
     * tiempo de respuesta del login no delate si un nombre de usuario existe o no.
     */
    private const HASH_SENUELO = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'usuario.required' => 'Debes indicar tu usuario.',
            'password.required' => 'Debes indicar tu contraseña.',
        ]);

        // El nombre de usuario es el campo `name`; se compara sin distinguir mayúsculas.
        $user = User::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($credentials['usuario']))])->first();

        // Hash::check() siempre se ejecuta (contra el hash real o, si el usuario no existe,
        // contra el señuelo) para no dar una pista de tiempo sobre si el usuario existe.
        if (! Hash::check($credentials['password'], $user->password ?? self::HASH_SENUELO) || ! $user) {
            throw ValidationException::withMessages([
                'usuario' => ['Las credenciales no son correctas.'],
            ]);
        }

        if (! $user->activo) {
            throw ValidationException::withMessages([
                'usuario' => ['Tu cuenta ha sido deshabilitada. Contacta a un administrador.'],
            ]);
        }

        // Sin "recordarme": no hay casilla para pedirlo, y este es un sistema de uso interno
        // (ej. recepcion del hotel) donde una sesion persistente en un equipo compartido es un
        // riesgo. La sesion normal ya dura SESSION_LIFETIME (ver .env) mientras haya actividad.
        Auth::login($user);

        $request->session()->regenerate();

        return response()->json(['user' => $this->serialize($user)]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->serialize($request->user())]);
    }

    private function serialize(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'rol' => $user->role?->nombre,
        ];
    }
}
