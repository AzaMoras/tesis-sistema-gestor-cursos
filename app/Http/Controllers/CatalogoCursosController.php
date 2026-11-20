<?php

namespace App\Http\Controllers;

use App\Services\CatalogoCursosService;
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

    public function show(string $curso): View
    {
        $id = filter_var($curso, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return view('publico.cursos.show', [
            'curso' => $this->catalogo->detalle($id), 'catalogo' => $this->catalogo,
        ]);
    }
}
