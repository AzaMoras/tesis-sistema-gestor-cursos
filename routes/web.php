<?php

use App\Http\Controllers\InicioController;
use App\Http\Controllers\RegistroController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\VerificacionCorreoController;
use App\Http\Middleware\VerificacionCorreoHabilitada;
use Illuminate\Support\Facades\Route;

Route::get('/', InicioController::class)->name('inicio');

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
