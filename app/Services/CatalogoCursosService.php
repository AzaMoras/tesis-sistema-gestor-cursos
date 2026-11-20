<?php

namespace App\Services;

use App\Models\CategoriaCurso;
use App\Models\Curso;
use App\Models\Edicion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Lectura pública: no concede acceso académico ni confirma plazas o inscripciones. */
class CatalogoCursosService
{
    public const MODALIDADES = ['autogestionado' => 'Autogestionado', 'acompanada' => 'Con acompañamiento'];

    public function cursos(): Builder
    {
        return Curso::query()
            ->select(['id', 'institucion_id', 'categoria_id', 'titulo', 'descripcion', 'objetivos', 'estado'])
            ->where('estado', config('operacion.publicacion.cursos'))
            ->whereHas('institucion', fn ($q) => $q->where('estado', 'activa'))
            ->whereHas('categoria', fn ($q) => $q->where('estado', 'activa')
                ->whereColumn('categorias_curso.institucion_id', 'cursos.institucion_id'));
    }

    private function edicionesPublicas(Builder $query): void
    {
        $query->where('estado', config('operacion.publicacion.ediciones'))
            ->whereIn('modalidad', array_keys(self::MODALIDADES));
    }

    public function conOferta(): Builder
    {
        return $this->cursos()->with([
            'institucion:id,nombre', 'categoria:id,nombre',
            'ediciones' => function ($q): void {
                $this->edicionesPublicas($q->getQuery());
                $q->select(['id', 'curso_id', 'clave', 'modalidad', 'fecha_inicio', 'fecha_fin',
                    'cierre_inscripciones_at', 'precio', 'moneda', 'asistencia_minima', 'calificacion_minima'])
                    ->orderBy('fecha_inicio')->orderBy('id');
            },
            'ediciones.grupos' => fn ($q) => $q->select(['id', 'edicion_id', 'codigo', 'cupo_maximo'])
                ->where('estado', config('operacion.publicacion.grupos'))->orderBy('codigo'),
        ]);
    }

    public function filtrar(Builder $query, array $filtros): Builder
    {
        if (($filtros['q'] ?? '') !== '') {
            // Los comodines del visitante se buscan como texto literal, con parámetros enlazados.
            $texto = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filtros['q']);
            $query->whereRaw("titulo LIKE ? ESCAPE '!'", ['%'.$texto.'%']);
        }
        if ($filtros['categoria'] ?? null) {
            $query->where('categoria_id', $filtros['categoria']);
        }
        if ($filtros['modalidad'] ?? null) {
            $query->whereHas('ediciones', function ($q) use ($filtros): void {
                $this->edicionesPublicas($q);
                $q->where('modalidad', $filtros['modalidad']);
            });
        }

        return $query;
    }

    public function categorias(): Collection
    {
        return CategoriaCurso::query()->select(['id', 'institucion_id', 'nombre'])
            ->whereIn('id', $this->cursos()->select('categoria_id'))
            ->with('institucion:id,nombre')->orderBy('nombre')->orderBy('id')->get();
    }

    public function modalidades(): array
    {
        $presentes = Edicion::query()->whereIn('curso_id', $this->cursos()->select('id'))
            ->where('estado', config('operacion.publicacion.ediciones'))
            ->whereIn('modalidad', array_keys(self::MODALIDADES))->distinct()->pluck('modalidad')->all();

        return array_intersect_key(self::MODALIDADES, array_flip($presentes));
    }

    public function detalle(int $id): Curso
    {
        $curso = $this->conOferta()->findOrFail($id);
        $curso->loadCount('requisitos');
        $curso->load([
            'requisitos' => fn ($q) => $q->select(['requisitos.id', 'nombre', 'descripcion', 'requiere_documento'])
                ->where('requisitos.institucion_id', $curso->institucion_id)->where('estado', 'activo')->orderBy('nombre'),
        ]);
        $curso->loadMissing([
            // Solo el horario programado; nunca enlaces de reuniones ni datos de participantes.
            'ediciones.grupos.sesiones' => fn ($q) => $q->select(['id', 'grupo_id', 'inicio_at', 'fin_at'])
                ->where('estado', 'programada')->orderBy('inicio_at'),
        ]);

        return $curso;
    }

    public function enPeriodoInscripcion(Edicion $edicion): bool
    {
        return $edicion->fecha_inicio !== null && $edicion->fecha_fin !== null
            && $edicion->fecha_fin->gte($edicion->fecha_inicio)
            && $edicion->fecha_fin->gte(today())
            && ($edicion->cierre_inscripciones_at === null || $edicion->cierre_inscripciones_at->isFuture());
    }

    public function resumen(Curso $curso): array
    {
        $ofertas = $curso->ediciones->filter(fn ($edicion) => $this->enPeriodoInscripcion($edicion)
            && $edicion->grupos->contains(fn ($grupo) => $grupo->cupo_maximo > 0));

        return [
            'modalidades' => $curso->ediciones->pluck('modalidad')->unique()
                ->map(fn ($modalidad) => self::MODALIDADES[$modalidad]),
            'precios' => $ofertas->unique(fn ($edicion) => $edicion->precio.'|'.$edicion->moneda),
            'hayOferta' => $ofertas->isNotEmpty(),
        ];
    }
}
