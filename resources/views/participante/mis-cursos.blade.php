@extends('layouts.sitio-publico')
@section('titulo', 'Mis cursos')

@section('contenido')
    <section class="container catalogo-presentacion" aria-labelledby="mis-cursos-titulo">
        <p class="eyebrow">TU FORMACIÓN VIRTUAL</p><h1 id="mis-cursos-titulo">Mis cursos</h1>
        <p>Consulta tus inscripciones y su estado. Los registros cancelados se conservan como historial.</p>
        <p class="text-muted">Fechas en dd/mm/aaaa y horas de 24 horas en {{ config('app.timezone') }}@if (config('app.timezone') === 'UTC') (tiempo universal coordinado; la hora local de México es diferente)@endif.</p>
        <div class="button-row"><a class="btn btn-primary" href="{{ route('cursos.index') }}">Explorar cursos <x-icon name="arrow-right" :size="18" /></a><a class="btn btn-outline" href="{{ route('cuenta') }}">Mi cuenta</a></div>
    </section>
    <section class="container catalogo-contenido" aria-label="Tus inscripciones">
        @if ($inscripciones->isEmpty())
            <div class="surface-card catalog-empty"><span class="icon-bubble"><x-icon name="book" :size="26" /></span><div><h2>Todavía no tienes inscripciones</h2><p>Explora el catálogo y consulta las condiciones de los grupos gratuitos abiertos antes de confirmar tu inscripción.</p></div></div>
        @else
            <div class="catalogo-tarjetas">
                @foreach ($inscripciones as $inscripcion)
                    @php($grupo = $inscripcion->grupo)
                    @php($edicion = $grupo?->edicion)
                    @php($curso = $edicion?->curso)
                    <article class="curso-publico mis-cursos-tarjeta">
                        <span class="tag">Estado de inscripción: {{ $inscripcion->estado }}</span>
                        <h2>{{ $curso?->titulo ?? 'Curso no disponible' }}</h2>
                        <p class="curso-institucion">{{ $curso?->institucion?->nombre ?? 'Institución no disponible' }}</p>
                        <dl class="mis-cursos-datos">
                            <div><dt>Edición</dt><dd>{{ $edicion?->clave ?? 'No disponible' }}</dd></div>
                            <div><dt>Grupo</dt><dd>{{ $grupo?->codigo ?? 'No disponible' }}</dd></div>
                            <div><dt>Modalidad virtual</dt><dd>{{ \App\Services\CatalogoCursosService::MODALIDADES[$edicion?->modalidad] ?? 'Por confirmar' }}</dd></div>
                            <div><dt>Fecha de inscripción ({{ config('app.timezone') }})</dt><dd>{{ $inscripcion->fecha_inscripcion?->format('d/m/Y H:i') ?? 'Sin fecha registrada' }}</dd></div>
                            @if ($inscripcion->fecha_cancelacion)<div><dt>Fecha de cancelación ({{ config('app.timezone') }})</dt><dd>{{ $inscripcion->fecha_cancelacion->format('d/m/Y H:i') }}</dd></div>@endif
                        </dl>
                        @if ($curso && $cursosPublicos->contains($curso->id))
                            <a class="btn btn-outline" href="{{ route('cursos.show', $curso->id) }}">Ver detalles del curso <x-icon name="arrow-right" :size="18" /></a>
                        @else
                            <p class="text-muted">El detalle público de este curso ya no está disponible. Tu registro se conserva.</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
        @if ($inscripciones->hasPages())
            <nav class="catalogo-paginacion" aria-label="Paginación de mis cursos">
                @if ($inscripciones->previousPageUrl())<a class="btn btn-outline" href="{{ $inscripciones->previousPageUrl() }}" rel="prev">Anterior</a>@endif
                <span>Página {{ $inscripciones->currentPage() }} de {{ $inscripciones->lastPage() }}</span>
                @if ($inscripciones->hasMorePages())<a class="btn btn-outline" href="{{ $inscripciones->nextPageUrl() }}" rel="next">Siguiente</a>@endif
            </nav>
        @endif
    </section>
@endsection
