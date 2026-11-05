<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class InicioController extends Controller
{
    public function __invoke(): View
    {
        return view('publico.inicio');
    }
}
