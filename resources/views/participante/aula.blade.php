@extends('layouts.sitio-publico')
@section('titulo', 'Aula virtual')
@section('clase_cuerpo', 'sitio-publico pagina-aula')
@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/aula.css') }}">
@endpush
@section('contenido')
    @php($cursoAula = $inscripcion->grupo?->edicion?->curso)
    <div class="container aula">
        <nav class="aula-migas" aria-label="Navegación del aula"><a href="{{ route('mis-cursos') }}">Mis cursos</a><span aria-hidden="true">/</span><span aria-current="page">Aula virtual</span></nav>
        <header class="aula-cabecera">
            <span class="aula-emblema"><x-icon name="graduation" :size="32" /></span>
            <div><p class="eyebrow">TU ESPACIO DE APRENDIZAJE · 100 % VIRTUAL</p><h1>{{ $cursoAula?->titulo ?? 'Aula no disponible' }}</h1><p>{{ $cursoAula?->institucion?->nombre ?? 'Institución no disponible' }}</p></div>
        </header>
        <p class="aula-aviso" @if (! $permitido) role="alert" @endif>{{ $mensaje }}</p>
        @if ($permitido)
            <div class="aula-estructura">
                <aside class="aula-indice" aria-labelledby="indice-titulo">
                    <h2 id="indice-titulo">Contenido del curso</h2>
                    <p class="text-muted">{{ $modulos->count() }} {{ $modulos->count() === 1 ? 'módulo disponible' : 'módulos disponibles' }}</p>
                    <ol>@foreach ($modulos as $modulo)<li><a href="#modulo-{{ $modulo->id }}">{{ $modulo->orden }}. {{ $modulo->titulo }}</a></li>@endforeach</ol>
                    <a class="btn btn-outline" href="{{ route('mis-cursos') }}">Volver a Mis cursos</a>
                </aside>
                <section class="aula-contenidos" aria-label="Módulos y recursos educativos">
                    <div class="aula-introduccion"><h2>Comienza a explorar</h2><p>Consulta cada módulo y abre los recursos publicados por tu institución. Los enlaces externos se abren en una pestaña nueva.</p></div>
                    @forelse ($modulos as $modulo)
                        <article class="aula-modulo" id="modulo-{{ $modulo->id }}" aria-labelledby="titulo-modulo-{{ $modulo->id }}" tabindex="-1">
                            <div class="aula-modulo-cabecera"><span class="aula-numero" aria-hidden="true">{{ $modulo->orden }}</span><div><span class="tag">{{ $modulo->obligatorio ? 'Obligatorio' : 'Complementario' }}</span><h3 id="titulo-modulo-{{ $modulo->id }}">{{ $modulo->titulo }}</h3></div></div>
                            @if ($modulo->descripcion)<p class="contenido-descripcion">{{ $modulo->descripcion }}</p>@endif
                            <ul class="aula-materiales">
                                @forelse ($modulo->materiales as $material)
                                    <li><span class="aula-recurso-icono"><x-icon :name="match ($material->tipo) { 'video' => 'play', 'documento' => 'file', default => 'book' }" :size="22" /></span><div><p class="aula-tipo">{{ \App\Services\ContenidoCursoService::TIPOS[$material->tipo] }}</p><a href="{{ $material->url_externa }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{ $material->titulo }}<x-icon name="arrow-right" :size="18" /><span class="sr-only"> (abre una pestaña nueva)</span></a></div></li>
                                @empty
                                    <li class="aula-vacio">Este módulo todavía no tiene materiales publicados. Consulta nuevamente más adelante.</li>
                                @endforelse
                            </ul>
                        </article>
                    @empty
                        <div class="aula-modulo aula-vacio"><x-icon name="layers" :size="30" /><h3>El contenido está en preparación</h3><p>Tu inscripción está registrada. La institución aún no ha agregado módulos a este curso.</p></div>
                    @endforelse
                </section>
            </div>
        @else
            <div class="aula-modulo"><h2>Acceso al contenido restringido</h2><p>Consulta el estado de tu inscripción en Mis cursos. Si necesitas ayuda, contacta a tu institución.</p><a class="btn btn-primary" href="{{ route('mis-cursos') }}">Volver a Mis cursos</a></div>
        @endif
        <footer class="aula-pie"><a href="{{ route('cursos.index') }}">Explorar el catálogo</a><span>Los recursos pertenecen a sus respectivos autores y proveedores.</span></footer>
    </div>
@endsection
