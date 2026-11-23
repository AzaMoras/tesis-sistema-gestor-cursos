<?php

namespace App\Http\Controllers;

use App\Services\GestionOfertaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CuentaController extends Controller
{
    public function __invoke(Request $request, GestionOfertaService $oferta): View
    {
        $usuario = $request->user();
        $puedeGestionar = $oferta->institucionesAutorizadas($usuario)->isNotEmpty();
        $participante = $usuario->participante;
        $mostrarMisCursos = false;

        if ($participante) {
            $roles = $usuario->roles()->pluck('roles.clave');
            $esPersonal = $roles->intersect(['administrador', 'coordinador', 'recepcion', 'instructor'])->isNotEmpty();
            $mostrarMisCursos = ! $esPersonal
                || $roles->contains('participante')
                || $participante->inscripciones()->exists();
        }

        return view('auth.cuenta', compact('usuario', 'puedeGestionar', 'mostrarMisCursos'));
    }
}
