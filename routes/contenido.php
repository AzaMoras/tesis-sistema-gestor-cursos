<?php

use App\Http\Controllers\ContenidoCursoController;
use Illuminate\Support\Facades\Route;

Route::prefix('gestion/{institucion}')->whereNumber('institucion')
    ->middleware(['auth:web', 'cuenta.activa', 'rol:administrador,coordinador', 'permiso:cursos.gestionar'])
    ->name('gestion.contenido.')->group(function (): void {
        Route::get('contenido', [ContenidoCursoController::class, 'index'])->name('index');
        Route::prefix('cursos/{curso}/contenido')->whereNumber('curso')->group(function (): void {
            Route::get('/', [ContenidoCursoController::class, 'curso'])->name('curso');
            Route::get('modulos/crear', [ContenidoCursoController::class, 'formulario'])->name('modulo.create');
            Route::post('modulos', [ContenidoCursoController::class, 'guardar'])->name('modulo.store');
            Route::prefix('modulos/{modulo}')->whereNumber('modulo')->group(function (): void {
                Route::get('/', [ContenidoCursoController::class, 'modulo'])->name('modulo.show');
                Route::get('editar', [ContenidoCursoController::class, 'formulario'])->name('modulo.edit');
                Route::put('/', [ContenidoCursoController::class, 'guardar'])->name('modulo.update');
                Route::get('materiales/crear', [ContenidoCursoController::class, 'formulario'])->defaults('entidad', 'material')->name('material.create');
                Route::post('materiales', [ContenidoCursoController::class, 'guardar'])->defaults('entidad', 'material')->name('material.store');
                Route::get('materiales/{material}/editar', [ContenidoCursoController::class, 'formulario'])->whereNumber('material')->defaults('entidad', 'material')->name('material.edit');
                Route::put('materiales/{material}', [ContenidoCursoController::class, 'guardar'])->whereNumber('material')->defaults('entidad', 'material')->name('material.update');
                Route::get('materiales/{material}', [ContenidoCursoController::class, 'formulario'])->whereNumber('material')->defaults('entidad', 'material')->defaults('lectura', true)->name('material.show');
                Route::post('materiales/{material}/publicar', [ContenidoCursoController::class, 'publicar'])->whereNumber('material')->defaults('publicar', true)->name('material.publish');
                Route::post('materiales/{material}/ocultar', [ContenidoCursoController::class, 'publicar'])->whereNumber('material')->defaults('publicar', false)->name('material.hide');
            });
        });
    });
