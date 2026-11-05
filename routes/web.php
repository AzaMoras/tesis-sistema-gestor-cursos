<?php

use App\Http\Controllers\InicioController;
use Illuminate\Support\Facades\Route;

Route::get('/', InicioController::class)->name('inicio');

require __DIR__.'/tesis_vistas_demo.php';
