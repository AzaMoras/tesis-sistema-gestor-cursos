<?php

namespace App\Http\Controllers;

use App\Services\AulaVirtualService;
use Illuminate\Http\Request;

class AulaVirtualController extends Controller
{
    public function __invoke(Request $request, int $inscripcion, AulaVirtualService $aula)
    {
        $acceso = $aula->consultar($request->user(), $inscripcion);
        $modulos = $acceso['permitido'] ? $aula->modulos($acceso['inscripcion']) : collect();

        return response()->view('participante.aula', $acceso + compact('modulos'), $acceso['permitido'] ? 200 : 403)
            ->header('Cache-Control', 'private, no-store');
    }
}
