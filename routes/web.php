<?php

use App\Http\Controllers\InicioController;
use App\Http\Controllers\SesionController;
use Illuminate\Support\Facades\Route;

Route::get('/', InicioController::class)->name('inicio');

Route::middleware('guest:web')->group(function (): void {
    Route::get('/login', [SesionController::class, 'create'])->name('login');
    Route::post('/login', [SesionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::post('/logout', [SesionController::class, 'destroy'])->middleware('auth:web')->name('logout');
Route::view('/mi-cuenta', 'auth.cuenta')->middleware(['auth:web', 'cuenta.activa'])->name('cuenta');

require __DIR__.'/tesis_vistas_demo.php';
