<?php

use App\Http\Controllers\AusenciaController;
use App\Http\Controllers\CursoController;
use App\Http\Controllers\DescuentoController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\RespaldoController;
use App\Http\Controllers\SaldoController;
use App\Http\Controllers\SolicitudController;
use Illuminate\Support\Facades\Route;

// La página de entrada lleva al inicio (si no hay sesión, pide el inicio de sesión)
Route::redirect('/', '/dashboard');

Route::get('/dashboard', InicioController::class)
    ->middleware(['auth', 'verified'])->name('dashboard');

// Cualquier usuario con sesión iniciada
Route::middleware('auth')->group(function () {
    Route::get('/saldos', [SaldoController::class, 'index'])->name('saldos.index');
    Route::get('/saldos/{docente}', [SaldoController::class, 'show'])->name('saldos.show');

    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/saldos', [ReporteController::class, 'saldos'])->name('reportes.saldos');
    Route::get('/reportes/ausencias', [ReporteController::class, 'ausencias'])->name('reportes.ausencias');

    // Solo la directora (el guardia 'directora' revisa el rol)
    Route::middleware('directora')->group(function () {
        Route::get('/ausencias', [AusenciaController::class, 'index'])->name('ausencias.index');
        Route::post('/ausencias/{ausencia}/anular', [AusenciaController::class, 'anular'])->name('ausencias.anular');
        Route::get('/ausencias/nueva', [AusenciaController::class, 'create'])->name('ausencias.create');
        Route::post('/ausencias', [AusenciaController::class, 'store'])->name('ausencias.store');
        Route::post('/saldos/{docente}/usar', [SaldoController::class, 'usar'])->name('saldos.usar');
        Route::post('/saldos/{docente}/ajustar', [SaldoController::class, 'ajustar'])->name('saldos.ajustar');
        Route::post('/movimientos/{movimiento}/revertir', [SaldoController::class, 'revertir'])->name('movimientos.revertir');

        Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
        Route::post('/solicitudes', [SolicitudController::class, 'store'])->name('solicitudes.store');
        Route::post('/solicitudes/{solicitud}/aprobar', [SolicitudController::class, 'aprobar'])->name('solicitudes.aprobar');
        Route::post('/solicitudes/{solicitud}/rechazar', [SolicitudController::class, 'rechazar'])->name('solicitudes.rechazar');

        Route::get('/personal', [PersonalController::class, 'index'])->name('personal.index');
        Route::post('/personal', [PersonalController::class, 'store'])->name('personal.store');
        Route::post('/personal/{docente}/baja', [PersonalController::class, 'baja'])->name('personal.baja');
        Route::post('/personal/{docente}/reactivar', [PersonalController::class, 'reactivar'])->name('personal.reactivar');

        Route::get('/respaldo', [RespaldoController::class, 'index'])->name('respaldo.index');
        Route::post('/respaldo/descargar', [RespaldoController::class, 'descargar'])->name('respaldo.descargar');

        Route::get('/descuentos', [DescuentoController::class, 'index'])->name('descuentos.index');
        Route::post('/descuentos', [DescuentoController::class, 'store'])->name('descuentos.store');
        Route::post('/descuentos/{descuento}/revertir', [DescuentoController::class, 'revertir'])->name('descuentos.revertir');

        Route::get('/curso', [CursoController::class, 'index'])->name('curso.index');
        Route::post('/curso/cerrar', [CursoController::class, 'cerrar'])->name('curso.cerrar');
        Route::post('/curso/reabrir', [CursoController::class, 'reabrir'])->name('curso.reabrir');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
