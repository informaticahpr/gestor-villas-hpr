<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    // Ademas de SANCTUM_STATEFUL_DOMAINS se agrega siempre el dominio de FRONTEND_URL: asi, si se
    // cambia el dominio del frontend (ej. en Railway) basta con actualizar FRONTEND_URL y el login
    // sigue funcionando. Cada entrada se limpia de "https://" y "/" finales, que Sanctum no admite
    // (con ellos la peticion no se reconoce como del frontend y el login falla con error 500).
    'stateful' => array_values(array_unique(array_filter(array_map(
        fn (string $dominio) => rtrim(preg_replace('#^https?://#', '', trim($dominio)), '/'),
        [
            ...explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
                '%s%s',
                'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
                Sanctum::currentApplicationUrlWithPort(),
                // Sanctum::currentRequestHost(),
            ))),
            (string) env('FRONTEND_URL', ''),
        ],
    )))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
