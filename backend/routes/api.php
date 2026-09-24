<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BitacoraController;
use App\Http\Controllers\Api\ConceptoController;
use App\Http\Controllers\Api\CuotaMantenimientoController;
use App\Http\Controllers\Api\FormaPagoController;
use App\Http\Controllers\Api\HoyController;
use App\Http\Controllers\Api\MovimientoController;
use App\Http\Controllers\Api\ReciboController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VillaController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/hoy', HoyController::class);

    Route::get('/villas', [VillaController::class, 'index']);
    Route::get('/villas/{villa}', [VillaController::class, 'show']);
    Route::post('/villas', [VillaController::class, 'store']);

    Route::get('/conceptos', [ConceptoController::class, 'index']);

    Route::get('/formas-pago', [FormaPagoController::class, 'index']);

    Route::get('/movimientos', [MovimientoController::class, 'index']);
    Route::post('/movimientos', [MovimientoController::class, 'store']);
    Route::post('/movimientos/aplicar-a-todas', [MovimientoController::class, 'aplicarATodas']);
    Route::get('/movimientos/{movimiento}/recibo', [ReciboController::class, 'pdf']);

    Route::get('/reportes/estado-cuenta', [ReporteController::class, 'estadoCuenta']);
    Route::get('/reportes/estado-cuenta/exportar', [ReporteController::class, 'exportarEstadoCuentaXlsx']);
    Route::get('/reportes/estado-cuenta/pdf', [ReporteController::class, 'estadoCuentaPdf']);
    Route::get('/reportes/saldos-generales', [ReporteController::class, 'saldosGenerales']);
    Route::get('/reportes/saldos-generales/exportar', [ReporteController::class, 'exportarSaldosXlsx']);
    Route::get('/reportes/saldos-generales/pdf', [ReporteController::class, 'saldosGeneralesPdf']);
    Route::get('/reportes/antiguedad-saldos', [ReporteController::class, 'antiguedadSaldos']);
    Route::get('/reportes/antiguedad-saldos/exportar', [ReporteController::class, 'exportarAntiguedadXlsx']);
    Route::get('/reportes/antiguedad-saldos/pdf', [ReporteController::class, 'antiguedadSaldosPdf']);

    Route::middleware('role:'.Role::DIRECTOR.','.Role::ADMIN)->group(function () {
        Route::put('/villas/{villa}', [VillaController::class, 'update']);
        Route::patch('/villas/{villa}/cuota-especial', [VillaController::class, 'actualizarCuotaEspecial']);
        Route::post('/conceptos', [ConceptoController::class, 'store']);
        Route::put('/conceptos/{concepto}', [ConceptoController::class, 'update']);
        Route::patch('/conceptos/{concepto}/activo', [ConceptoController::class, 'activar']);
        Route::delete('/conceptos/{concepto}', [ConceptoController::class, 'destroy']);

        Route::post('/formas-pago', [FormaPagoController::class, 'store']);
        Route::put('/formas-pago/{formaPago}', [FormaPagoController::class, 'update']);
        Route::patch('/formas-pago/{formaPago}/activo', [FormaPagoController::class, 'activar']);
        Route::delete('/formas-pago/{formaPago}', [FormaPagoController::class, 'destroy']);
        // el Director puede ver la lista de usuarios, pero no modificarlos (ver mas abajo)
        Route::get('/usuarios', [UserController::class, 'index']);

        Route::get('/cuota-mantenimiento', [CuotaMantenimientoController::class, 'show']);
        Route::put('/cuota-mantenimiento', [CuotaMantenimientoController::class, 'update']);

        Route::get('/bitacora', [BitacoraController::class, 'index']);
        Route::get('/bitacora/exportar', [BitacoraController::class, 'exportarExcel']);
        Route::get('/bitacora/pdf', [BitacoraController::class, 'exportarPdf']);
    });

    // Solo el Administrador gestiona usuarios: crear, editar, habilitar/deshabilitar, eliminar y
    // cambiar contraseñas. El Director unicamente puede verlos (GET /usuarios).
    Route::middleware('role:'.Role::ADMIN)->group(function () {
        Route::post('/usuarios', [UserController::class, 'store']);
        Route::put('/usuarios/{user}', [UserController::class, 'update']);
        Route::patch('/usuarios/{user}/activo', [UserController::class, 'activar']);
        Route::delete('/usuarios/{user}', [UserController::class, 'destroy']);
        Route::patch('/usuarios/{user}/password', [UserController::class, 'cambiarPassword']);
    });
});
