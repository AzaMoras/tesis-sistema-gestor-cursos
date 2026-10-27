<?php

use App\Http\Middleware\AdministradorMiddleware;
use App\Http\Middleware\CoordinadorMiddleware;
use App\Http\Middleware\CuentaActivaMiddleware;
use App\Http\Middleware\InstructorMiddleware;
use App\Http\Middleware\ParticipanteMiddleware;
use App\Http\Middleware\PermisoMiddleware;
use App\Http\Middleware\RecepcionMiddleware;
use App\Http\Middleware\RolMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'cuenta.activa' => CuentaActivaMiddleware::class,
            'rol' => RolMiddleware::class,
            'administrador' => AdministradorMiddleware::class,
            'coordinador' => CoordinadorMiddleware::class,
            'recepcion' => RecepcionMiddleware::class,
            'instructor' => InstructorMiddleware::class,
            'participante' => ParticipanteMiddleware::class,
            'permiso' => PermisoMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
