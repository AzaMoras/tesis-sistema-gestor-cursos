@extends('gestion.contenido.base')
@section('contenido-gestion')
    <div class="gestion-toolbar">
        <a class="btn btn-outline" href="{{ route('gestion.contenido.index', $institucion->id) }}">Volver a los cursos</a>
        @unless ($protegido)<a class="btn btn-primary" href="{{ route('gestion.contenido.modulo.create', [$institucion->id, $curso->id]) }}"><x-icon name="plus" :size="18" />Crear módulo</a>@endunless
    </div>
    <form method="GET" class="gestion-filtros">
        <div><label for="q">Buscar módulo</label><input id="q" name="q" maxlength="180" value="{{ request('q') }}"></div>
        <button class="btn btn-outline" type="submit">Buscar</button>
        <a class="btn btn-outline btn-limpiar" href="{{ route('gestion.contenido.curso', [$institucion->id, $curso->id]) }}"><x-icon name="broom" :size="18" />Limpiar</a>
    </form>
    <ul class="gestion-lista">
        @forelse ($modulos as $moduloListado)
            <li class="gestion-registro">
                <div class="gestion-registro-cabecera"><h3>{{ $moduloListado->orden }}. {{ $moduloListado->titulo }}</h3><span class="gestion-estado">{{ $moduloListado->obligatorio ? 'Obligatorio' : 'Complementario' }}</span></div>
                <p class="contenido-descripcion">{{ $moduloListado->descripcion ?: 'Sin descripción adicional.' }}</p>
                <p class="text-muted">{{ $moduloListado->materiales_count }} materiales registrados</p>
                <div class="gestion-acciones">
                    <a class="btn btn-primary" href="{{ route('gestion.contenido.modulo.show', [$institucion->id, $curso->id, $moduloListado->id]) }}"><x-icon name="book" :size="18" />Ver materiales<span class="sr-only"> de {{ $moduloListado->titulo }}</span></a>
                    @unless ($protegido)<a class="btn btn-outline" href="{{ route('gestion.contenido.modulo.edit', [$institucion->id, $curso->id, $moduloListado->id]) }}"><x-icon name="edit" :size="18" />Editar módulo<span class="sr-only"> {{ $moduloListado->titulo }}</span></a>@endunless
                </div>
            </li>
        @empty
            <li class="gestion-registro">No hay módulos que mostrar. Crea el primer módulo o cambia la búsqueda.</li>
        @endforelse
    </ul>
    {{ $modulos->links() }}
@endsection
