<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->limitarIntentosDeLogin();
    }

    /**
     * Limite de intentos en /api/login, para dificultar la adivinanza de contraseñas por fuerza
     * bruta. Combina dos topes: por usuario+IP (5/min, el mas estricto, protege una cuenta
     * puntual) y por IP (20/min, evita que una sola IP recorra muchos usuarios).
     */
    private function limitarIntentosDeLogin(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $usuario = mb_strtolower(trim((string) $request->input('usuario')));

            return [
                Limit::perMinute(5)->by("login:{$usuario}:{$request->ip()}")->response(
                    fn (Request $request, array $headers) => response()->json([
                        'message' => 'Demasiados intentos con este usuario. Espera un minuto antes de volver a intentarlo.',
                    ], 429, $headers)
                ),
                Limit::perMinute(20)->by("login-ip:{$request->ip()}")->response(
                    fn (Request $request, array $headers) => response()->json([
                        'message' => 'Demasiados intentos. Espera un minuto antes de volver a intentarlo.',
                    ], 429, $headers)
                ),
            ];
        });
    }
}
