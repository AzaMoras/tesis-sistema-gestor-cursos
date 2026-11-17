<?php

use Illuminate\Support\Facades\Route;

// Prototipos Blade: las rutas públicas no requieren cuenta ni asignaciones.
Route::prefix('_demo-tesis')->group(function (): void {
    Route::view('/', 'tesis.inicio');
    Route::view('/cursos', 'tesis.catalogo');

    // Prototipos privados: autenticación y autorización institucional en servidor.
    // Sin institución explícita se exige una única institución activa asignada.
    Route::middleware(['auth:web', 'cuenta.activa'])->group(function (): void {
        Route::view('/estudiante/{institucion?}', 'tesis.estudiante.inicio')
            ->whereNumber('institucion')
            ->middleware(['verified', 'participante']);

        // El portal de gestión es compartido: los cuatro roles son alternativas.
        // Las futuras acciones deben añadir permiso:<clave> según permisos.clave.
        Route::view('/privado/{institucion?}', 'tesis.privado.inicio')
            ->whereNumber('institucion')
            ->middleware('rol:administrador,coordinador,recepcion,instructor');
    });
});
