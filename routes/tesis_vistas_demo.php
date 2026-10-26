<?php

use Illuminate\Support\Facades\Route;

// Rutas de ejemplo, aisladas bajo un prefijo para no sobrescribir las rutas actuales.
// Incluir desde routes/web.php SOLO al revisar el proyecto.
Route::prefix('_demo-tesis')->group(function (): void {
    Route::view('/', 'tesis.inicio');
    Route::view('/cursos', 'tesis.catalogo');

    // NO quitar auth: estas vistas representan un portal PRIVADO.
    // Cuando exista la autenticación, añadir middleware de permisos/policies.
    Route::middleware('auth')->group(function (): void {
        Route::view('/estudiante', 'tesis.estudiante.inicio');
        Route::view('/privado', 'tesis.privado.inicio');
    });
});
