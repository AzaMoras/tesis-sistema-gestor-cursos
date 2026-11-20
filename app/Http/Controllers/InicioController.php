<?php

namespace App\Http\Controllers;

use App\Services\CatalogoCursosService;
use Illuminate\View\View;

class InicioController extends Controller
{
    public function __invoke(CatalogoCursosService $catalogo): View
    {
        return view('publico.inicio', ['cursos' => $catalogo->conOferta()->orderByDesc('id')->limit(3)->get()]);
    }
}
