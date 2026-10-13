<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BitacoraController;
use App\Http\Controllers\Api\ConceptoController;
use App\Http\Controllers\Api\CuotaMantenimientoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FormaPagoController;
use App\Http\Controllers\Api\HoyController;
use App\Http\Controllers\Api\MovimientoController;
use App\Http\Controllers\Api\PermisoController;
use App\Http\Controllers\Api\PropietarioController;
use App\Http\Controllers\Api\ReciboController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VillaController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// activo: si deshabilitan al usuario con la sesion abierta, se le cierra en su siguiente peticion
Route::middleware(['auth:sanctum', 'activo'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/hoy', HoyController::class);
    // todos los cargos; los indicadores que recibe cada uno dependen de sus permisos (ver DashboardController)
    Route::get('/dashboard', DashboardController::class);

    // --- solo lectura: todos los cargos ---
    Route::get('/villas', [VillaController::class, 'index']);
    Route::get('/villas/{villa}', [VillaController::class, 'show']);

    Route::get('/propietarios', [PropietarioController::class, 'index']);

    Route::get('/conceptos', [ConceptoController::class, 'index']);

    Route::get('/formas-pago', [FormaPagoController::class, 'index']);

    Route::get('/movimientos', [MovimientoController::class, 'index']);
    Route::get('/movimientos/{movimiento}/recibo', [ReciboController::class, 'pdf']);

    Route::get('/reportes/estado-cuenta', [ReporteController::class, 'estadoCuenta']);
    Route::get('/reportes/estado-cuenta/exportar', [ReporteController::class, 'exportarEstadoCuentaXlsx']);
    Route::get('/reportes/estado-cuenta/pdf', [ReporteController::class, 'estadoCuentaPdf']);

    // --- segun los permisos del cargo (Configuracion -> Permisos; el Administrador los tiene todos) ---
    Route::post('/villas', [VillaController::class, 'store'])->middleware('permiso:villas.crear');
    Route::put('/villas/{villa}', [VillaController::class, 'update'])->middleware('permiso:villas.editar');

    Route::post('/movimientos', [MovimientoController::class, 'store'])->middleware('permiso:movimientos.aplicar');
    Route::post('/movimientos/aplicar-a-todas', [MovimientoController::class, 'aplicarATodas'])->middleware('permiso:movimientos.aplicar');
    Route::patch('/movimientos/{movimiento}/anular', [MovimientoController::class, 'anular'])->middleware('permiso:movimientos.anular');

    Route::middleware('permiso:reportes.generales')->group(function () {
        Route::get('/reportes/saldos-generales', [ReporteController::class, 'saldosGenerales']);
        Route::get('/reportes/saldos-generales/exportar', [ReporteController::class, 'exportarSaldosXlsx']);
        Route::get('/reportes/saldos-generales/pdf', [ReporteController::class, 'saldosGeneralesPdf']);
        Route::get('/reportes/antiguedad-saldos', [ReporteController::class, 'antiguedadSaldos']);
        Route::get('/reportes/antiguedad-saldos/exportar', [ReporteController::class, 'exportarAntiguedadXlsx']);
        Route::get('/reportes/antiguedad-saldos/pdf', [ReporteController::class, 'antiguedadSaldosPdf']);
        Route::get('/reportes/por-concepto', [ReporteController::class, 'porConcepto']);
        Route::get('/reportes/por-concepto/exportar', [ReporteController::class, 'exportarPorConceptoXlsx']);
        Route::get('/reportes/por-concepto/pdf', [ReporteController::class, 'porConceptoPdf']);
    });

    Route::patch('/villas/{villa}/alquilada', [VillaController::class, 'actualizarAlquilada'])->middleware('permiso:config.alquiladas');

    Route::middleware('permiso:config.conceptos')->group(function () {
        Route::post('/conceptos', [ConceptoController::class, 'store']);
        Route::put('/conceptos/{concepto}', [ConceptoController::class, 'update']);
        Route::patch('/conceptos/{concepto}/activo', [ConceptoController::class, 'activar']);
        Route::delete('/conceptos/{concepto}', [ConceptoController::class, 'destroy']);
    });

    Route::middleware('permiso:config.cuotas')->group(function () {
        Route::get('/cuota-mantenimiento', [CuotaMantenimientoController::class, 'show']);
        Route::put('/cuota-mantenimiento', [CuotaMantenimientoController::class, 'update']);
        Route::patch('/villas/{villa}/cuota-especial', [VillaController::class, 'actualizarCuotaEspecial']);
    });

    Route::middleware('permiso:config.formas_pago')->group(function () {
        Route::post('/formas-pago', [FormaPagoController::class, 'store']);
        Route::put('/formas-pago/{formaPago}', [FormaPagoController::class, 'update']);
        Route::patch('/formas-pago/{formaPago}/activo', [FormaPagoController::class, 'activar']);
        Route::delete('/formas-pago/{formaPago}', [FormaPagoController::class, 'destroy']);
    });

    Route::middleware('permiso:config.bitacora')->group(function () {
        Route::get('/bitacora', [BitacoraController::class, 'index']);
        Route::get('/bitacora/exportar', [BitacoraController::class, 'exportarExcel']);
        Route::get('/bitacora/pdf', [BitacoraController::class, 'exportarPdf']);
    });

    Route::get('/usuarios', [UserController::class, 'index'])->middleware('permiso:usuarios.ver,usuarios.gestionar');
    Route::middleware('permiso:usuarios.gestionar')->group(function () {
        Route::post('/usuarios', [UserController::class, 'store']);
        Route::put('/usuarios/{user}', [UserController::class, 'update']);
        Route::patch('/usuarios/{user}/activo', [UserController::class, 'activar']);
        Route::delete('/usuarios/{user}', [UserController::class, 'destroy']);
        Route::patch('/usuarios/{user}/password', [UserController::class, 'cambiarPassword']);
    });

    // Permisos: solo el Administrador, y no es un permiso que se pueda dar (nadie mas se los sube solo)
    Route::middleware('role:'.Role::ADMIN)->group(function () {
        Route::get('/permisos', [PermisoController::class, 'index']);
        Route::put('/permisos/{rol}', [PermisoController::class, 'update']);
    });
});
