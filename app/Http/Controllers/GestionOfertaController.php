<?php

namespace App\Http\Controllers;

use App\Services\GestionOfertaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GestionOfertaController extends Controller
{
    public function __construct(private GestionOfertaService $oferta) {}

    public function instituciones(Request $request)
    {
        $instituciones = $this->oferta->institucionesAutorizadas($request->user());

        return view('gestion.instituciones', compact('instituciones'));
    }

    private function contexto(Request $request): array
    {
        $institucion = $request->attributes->get('institucion_autorizada');
        $this->oferta->autorizar($request->user(), $institucion->id);

        return [$institucion, $request->route('tipo')];
    }

    public function index(Request $request)
    {
        [$institucion, $tipo] = $this->contexto($request);
        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:180'], 'estado' => ['nullable', Rule::in(['activa', 'borrador', 'publicado', 'abierta'])]]);
        $registros = $this->oferta->consulta($tipo, $institucion->id)
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->where(match ($tipo) {
                'categorias' => 'nombre', 'cursos' => 'titulo', 'ediciones' => 'clave', 'grupos' => 'codigo',
            }, 'like', '%'.$texto.'%'))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return view('gestion.index', compact('institucion', 'tipo', 'registros'))->with('oferta', $this->oferta);
    }

    public function formulario(Request $request)
    {
        [$institucion, $tipo] = $this->contexto($request);
        $id = $request->route('registro');
        $registro = $id !== null ? $this->oferta->consulta($tipo, $institucion->id)->findOrFail($id) : null;
        $soloLectura = (bool) $request->route('solo_lectura', false);
        $puedeEditar = ! $registro || $this->oferta->editable($tipo, $registro);
        $editable = $puedeEditar && ! $soloLectura;
        $cursoPublicado = $tipo === 'cursos' && $registro?->estado === config('operacion.publicacion.cursos');
        $camposCurso = $cursoPublicado ? $this->oferta->camposGeneralesCurso($registro) : null;
        $datos = $registro ? $this->oferta->datosDe($tipo, $registro) : [];
        $padres = match ($tipo) {
            'cursos' => $this->oferta->consulta('categorias', $institucion->id)->where('estado', 'activa')->get(),
            'ediciones' => $this->oferta->consulta('cursos', $institucion->id)->get(),
            'grupos' => $this->oferta->consulta('ediciones', $institucion->id)->get(),
            default => collect(),
        };

        return view('gestion.formulario', compact('institucion', 'tipo', 'registro', 'editable', 'datos', 'padres', 'soloLectura', 'puedeEditar', 'cursoPublicado', 'camposCurso'));
    }

    public function guardar(Request $request)
    {
        [$institucion, $tipo] = $this->contexto($request);
        $this->oferta->guardar($request->user(), $institucion->id, $tipo, $request->route('registro'), $request->all());

        return redirect()->route('gestion.'.$tipo.'.index', $institucion->id)->with('status', 'Registro guardado correctamente.');
    }

    public function publicar(Request $request)
    {
        [$institucion, $tipo] = $this->contexto($request);
        $this->oferta->publicar($request->user(), $institucion->id, $tipo, (int) $request->route('registro'));

        return redirect()->route('gestion.'.$tipo.'.index', $institucion->id)->with('status', 'Transición realizada correctamente.');
    }
}
