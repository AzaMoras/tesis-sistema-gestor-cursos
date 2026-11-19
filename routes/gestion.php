<?php

use App\Http\Controllers\GestionOfertaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:web', 'cuenta.activa'])->group(function (): void {
    Route::get('/gestion', [GestionOfertaController::class, 'instituciones'])->name('gestion.instituciones');
    Route::prefix('gestion/{institucion}')->whereNumber('institucion')
        ->middleware(['rol:administrador,coordinador', 'permiso:cursos.gestionar'])
        ->group(function (): void {
            foreach (['categorias', 'cursos', 'ediciones', 'grupos'] as $tipo) {
                Route::get($tipo, [GestionOfertaController::class, 'index'])->defaults('tipo', $tipo)->name('gestion.'.$tipo.'.index');
                Route::get($tipo.'/crear', [GestionOfertaController::class, 'formulario'])->defaults('tipo', $tipo)->name('gestion.'.$tipo.'.create');
                Route::post($tipo, [GestionOfertaController::class, 'guardar'])->defaults('tipo', $tipo)->name('gestion.'.$tipo.'.store');
                Route::get($tipo.'/{registro}/editar', [GestionOfertaController::class, 'formulario'])->whereNumber('registro')->defaults('tipo', $tipo)->name('gestion.'.$tipo.'.edit');
                Route::put($tipo.'/{registro}', [GestionOfertaController::class, 'guardar'])->whereNumber('registro')->defaults('tipo', $tipo)->name('gestion.'.$tipo.'.update');
                Route::get($tipo.'/{registro}', [GestionOfertaController::class, 'formulario'])->whereNumber('registro')
                    ->defaults('tipo', $tipo)->defaults('solo_lectura', true)->name('gestion.'.$tipo.'.show');
                if ($tipo !== 'categorias') {
                    Route::post($tipo.'/{registro}/publicar', [GestionOfertaController::class, 'publicar'])->whereNumber('registro')->defaults('tipo', $tipo)->name('gestion.'.$tipo.'.publish');
                }
            }
        });
});
