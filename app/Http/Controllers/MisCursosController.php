<?php

namespace App\Http\Controllers;

use App\Models\Inscripcion;
use App\Services\AulaVirtualService;
use App\Services\CatalogoCursosService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MisCursosController extends Controller
{
    public function __invoke(Request $request, CatalogoCursosService $catalogo, AulaVirtualService $aula): View
    {
        $participante = $request->user()->participante()->first();
        abort_unless($participante, 403, 'Tu cuenta no tiene un perfil de participante activo.');
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $registros = Inscripcion::query()->select(['id', 'participante_id', 'grupo_id', 'estado', 'fecha_inscripcion', 'fecha_cancelacion'])
            ->where('participante_id', $participante->id)
            ->with([
                'grupo' => fn ($q) => $q->withTrashed()->select(['id', 'edicion_id', 'codigo']),
                'grupo.edicion' => fn ($q) => $q->withTrashed()->select(['id', 'curso_id', 'clave', 'modalidad']),
                'grupo.edicion.curso' => fn ($q) => $q->withTrashed()->select(['id', 'institucion_id', 'titulo']),
                'grupo.edicion.curso.institucion' => fn ($q) => $q->withTrashed()->select(['id', 'nombre']),
            ])->orderByDesc('id')->paginate(12);
        $publicos = $catalogo->cursos()->whereIn('id', $registros->getCollection()
            ->map(fn ($registro) => $registro->grupo?->edicion?->curso_id)->filter())->pluck('id');

        $accesosAula = $registros->getCollection()->mapWithKeys(fn ($registro) => [$registro->id => $aula->disponibilidad($request->user(), $registro->id)]);

        return view('participante.mis-cursos', ['inscripciones' => $registros, 'cursosPublicos' => $publicos, 'accesosAula' => $accesosAula]);
    }
}
