<?php

namespace App\Http\Controllers;

use App\Services\CatalogoCursosService;
use App\Services\InscripcionService;
use App\Services\Soporte\RetornoInscripcion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogoCursosController extends Controller
{
    public function __construct(private CatalogoCursosService $catalogo) {}

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'categoria' => ['nullable', 'integer', 'min:1'],
            'modalidad' => ['nullable', Rule::in(array_keys(CatalogoCursosService::MODALIDADES))],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], [
            'string' => 'La búsqueda debe ser texto.', 'max' => 'El valor de :attribute es demasiado largo o grande.',
            'integer' => 'El valor de :attribute debe ser un número entero.',
            'min' => 'El valor de :attribute debe ser positivo.', 'in' => 'Selecciona una modalidad válida.',
        ]);
        $cursos = $this->catalogo->filtrar($this->catalogo->conOferta(), $filtros)
            ->orderBy('titulo')->orderBy('id')->paginate(12)->appends(array_intersect_key($filtros, array_flip(['q', 'categoria', 'modalidad'])));

        return view('publico.cursos.index', [
            'cursos' => $cursos, 'filtros' => $filtros,
            'categorias' => $this->catalogo->categorias(), 'modalidades' => $this->catalogo->modalidades(),
        ]);
    }

    public function show(Request $request, string $curso, InscripcionService $inscripciones): View
    {
        $id = filter_var($curso, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        $registro = $this->catalogo->detalle($id);
        $opciones = [];
        foreach ($registro->ediciones as $edicion) {
            $edicion->setRelation('curso', $registro);
            foreach ($edicion->grupos as $grupo) {
                $grupo->setRelation('edicion', $edicion);
                $opciones[$grupo->id] = $inscripciones->estadoGratuita($grupo, $request->user());
            }
        }

        return view('publico.cursos.show', ['curso' => $registro, 'catalogo' => $this->catalogo, 'opciones' => $opciones]);
    }

    public function acceso(Request $request, string $curso, RetornoInscripcion $retorno): RedirectResponse
    {
        $id = filter_var($curso, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);
        $this->catalogo->cursos()->findOrFail($id);
        $datos = $request->validate(['destino' => ['nullable', Rule::in(['login', 'register'])]]);
        if ($request->user()) {
            return redirect()->route('cursos.show', $id);
        }
        $retorno->recordar($request, $id);

        return redirect()->route($datos['destino'] ?? 'login');
    }
}
