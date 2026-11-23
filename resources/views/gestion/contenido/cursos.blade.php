@extends('gestion.contenido.base')
@section('contenido-gestion')
    <p>Selecciona un curso de esta institución para administrar sus módulos y materiales.</p>
    <form method="GET" class="gestion-filtros">
        <div><label for="q">Buscar curso</label><input id="q" name="q" maxlength="180" value="{{ request('q') }}"></div>
        <button class="btn btn-outline" type="submit">Buscar</button>
        <a class="btn btn-outline btn-limpiar" href="{{ route('gestion.contenido.index', $institucion->id) }}"><x-icon name="broom" :size="18" />Limpiar</a>
    </form>
    <ul class="gestion-lista">
        @forelse ($cursos as $cursoListado)
            <li class="gestion-registro">
                <div class="gestion-registro-cabecera"><h2>{{ $cursoListado->titulo }}</h2><span class="gestion-estado">{{ ucfirst($cursoListado->estado) }}</span></div>
                <p class="text-muted">{{ $cursoListado->categoria->nombre }} · {{ $cursoListado->modulos_count }} módulos</p>
                <div class="gestion-acciones"><a class="btn btn-primary" href="{{ route('gestion.contenido.curso', [$institucion->id, $cursoListado->id]) }}"><x-icon name="layers" :size="18" />Administrar contenido<span class="sr-only"> de {{ $cursoListado->titulo }}</span></a></div>
            </li>
        @empty
            <li class="gestion-registro">No hay cursos que coincidan. Crea un curso en la sección Cursos o cambia la búsqueda.</li>
        @endforelse
    </ul>
    {{ $cursos->links() }}
@endsection
