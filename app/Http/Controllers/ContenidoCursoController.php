<?php

namespace App\Http\Controllers;

use App\Services\ContenidoCursoService;
use App\Services\GestionOfertaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContenidoCursoController extends Controller
{
    public function __construct(private ContenidoCursoService $contenido) {}

    private function contexto(Request $request): array
    {
        $institucion = $request->attributes->get('institucion_autorizada');
        $curso = $this->contenido->curso($request->user(), $institucion->id, (int) $request->route('curso'));

        return compact('institucion', 'curso') + ['protegido' => $this->contenido->protegido($curso)];
    }

    public function index(Request $request, GestionOfertaService $oferta)
    {
        $institucion = $request->attributes->get('institucion_autorizada');
        $oferta->autorizar($request->user(), $institucion->id);
        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:180']]);
        $cursos = $oferta->consulta('cursos', $institucion->id)->withCount('modulos')
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->where('titulo', 'like', '%'.$texto.'%'))
            ->orderBy('titulo')->paginate(15)->withQueryString();

        return view('gestion.contenido.cursos', compact('institucion', 'cursos'));
    }

    public function curso(Request $request)
    {
        $contexto = $this->contexto($request);
        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:180']]);
        $modulos = $contexto['curso']->modulos()->withCount('materiales')
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->where('titulo', 'like', '%'.$texto.'%'))
            ->orderBy('orden')->paginate(12)->withQueryString();

        return view('gestion.contenido.modulos', $contexto + compact('modulos'));
    }

    public function modulo(Request $request)
    {
        $contexto = $this->contexto($request);
        $modulo = $this->contenido->modulo($contexto['curso'], (int) $request->route('modulo'));
        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:180'], 'publicado' => ['nullable', Rule::in(['0', '1'])]]);
        $materiales = $modulo->materiales()->when($filtros['q'] ?? null, fn ($q, $texto) => $q->where('titulo', 'like', '%'.$texto.'%'))
            ->when(isset($filtros['publicado']), fn ($q) => $q->where('publicado', $filtros['publicado']))
            ->orderBy('orden')->paginate(15)->withQueryString();

        return view('gestion.contenido.materiales', $contexto + compact('modulo', 'materiales'));
    }

    public function formulario(Request $request)
    {
        $contexto = $this->contexto($request);
        $esMaterial = $request->route('entidad') === 'material';
        $modulo = $request->route('modulo') ? $this->contenido->modulo($contexto['curso'], (int) $request->route('modulo')) : null;
        $registro = $esMaterial
            ? ($request->route('material') ? $this->contenido->material($modulo, (int) $request->route('material')) : null)
            : $modulo;
        $lectura = (bool) $request->route('lectura', false);
        $bloqueado = $contexto['protegido'] || ($esMaterial && $registro && ($registro->publicado || $registro->ruta_archivo !== null));
        $siguienteOrden = ($esMaterial ? $modulo->materiales()->withTrashed() : $contexto['curso']->modulos()->withTrashed())->max('orden') + 1;

        return view('gestion.contenido.formulario', $contexto + compact('esMaterial', 'modulo', 'registro', 'lectura', 'bloqueado', 'siguienteOrden'));
    }

    public function guardar(Request $request)
    {
        $contexto = $this->contexto($request);
        if ($request->route('entidad') === 'material') {
            $material = $this->contenido->guardarMaterial($request->user(), $contexto['institucion']->id, $contexto['curso']->id,
                (int) $request->route('modulo'), $request->route('material'), $request->all());

            return redirect()->route('gestion.contenido.material.show', [$contexto['institucion']->id, $contexto['curso']->id, $material->modulo_id, $material->id])
                ->with('status', 'Material guardado como borrador. Revísalo antes de publicarlo.');
        }
        $modulo = $this->contenido->guardarModulo($request->user(), $contexto['institucion']->id, $contexto['curso']->id, $request->route('modulo'), $request->all());

        return redirect()->route('gestion.contenido.modulo.show', [$contexto['institucion']->id, $contexto['curso']->id, $modulo->id])
            ->with('status', 'Módulo guardado correctamente.');
    }

    public function publicar(Request $request)
    {
        $contexto = $this->contexto($request);
        $publicar = (bool) $request->route('publicar');
        $this->contenido->publicarMaterial($request->user(), $contexto['institucion']->id, $contexto['curso']->id,
            (int) $request->route('modulo'), (int) $request->route('material'), $publicar);

        return back()->with('status', $publicar ? 'Material publicado para los participantes autorizados.' : 'Material oculto a los participantes.');
    }
}
