<?php

namespace App\Services\Soporte;

use App\Services\CatalogoCursosService;
use Illuminate\Http\Request;

final class RetornoInscripcion
{
    public function recordar(Request $request, int $curso): void
    {
        $request->session()->put('inscripcion.curso_id', $curso);
    }

    /** Solo un ID guardado por el servidor; nunca redirige a url.intended o a una URL enviada. */
    public function consumir(Request $request): ?string
    {
        $id = $request->session()->pull('inscripcion.curso_id');
        if (! is_int($id) || $id < 1 || ! app(CatalogoCursosService::class)->cursos()->whereKey($id)->exists()) {
            return null;
        }

        return route('cursos.show', $id).'#ediciones-titulo';
    }
}
