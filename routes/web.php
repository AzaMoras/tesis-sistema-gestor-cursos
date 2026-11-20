<?php

use App\Http\Controllers\CatalogoCursosController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\InscripcionGratuitaController;
use App\Http\Controllers\MisCursosController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\VerificacionCorreoController;
use App\Http\Middleware\VerificacionCorreoHabilitada;
use Illuminate\Support\Facades\Route;

Route::get('/', InicioController::class)->name('inicio');
Route::get('/cursos', [CatalogoCursosController::class, 'index'])->name('cursos.index');
Route::get('/cursos/{curso}', [CatalogoCursosController::class, 'show'])->whereNumber('curso')->name('cursos.show');
Route::get('/cursos/{curso}/acceso', [CatalogoCursosController::class, 'acceso'])->whereNumber('curso')->name('cursos.acceso');

Route::middleware(['auth:web', 'cuenta.activa', 'verified'])->group(function (): void {
    Route::post('/cursos/{curso}/grupos/{grupo}/inscripcion', [InscripcionGratuitaController::class, 'store'])
        ->whereNumber(['curso', 'grupo'])->middleware('throttle:inscripcion-gratuita')->name('inscripciones.gratuitas.store');
    Route::get('/mis-cursos', MisCursosController::class)->name('mis-cursos');
});

Route::middleware('guest:web')->group(function (): void {
    Route::get('/register', [RegistroController::class, 'create'])->name('register');
    Route::post('/register', [RegistroController::class, 'store'])->middleware('throttle:registro')->name('register.store');
    Route::get('/login', [SesionController::class, 'create'])->name('login');
    Route::post('/login', [SesionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [SesionController::class, 'destroy'])->middleware('auth:web')->name('logout');
Route::view('/mi-cuenta', 'auth.cuenta')->middleware(['auth:web', 'cuenta.activa'])->name('cuenta');

Route::middleware(['auth:web', 'cuenta.activa', VerificacionCorreoHabilitada::class])->group(function (): void {
    Route::get('/email/verify', [VerificacionCorreoController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerificacionCorreoController::class, 'verify'])
        ->middleware(['signed', 'throttle:verificacion'])->name('verification.verify');
    Route::post('/email/verification-notification', [VerificacionCorreoController::class, 'send'])
        ->middleware('throttle:verificacion')->name('verification.send');
});

require __DIR__.'/tesis_vistas_demo.php';
require __DIR__.'/gestion.php';
