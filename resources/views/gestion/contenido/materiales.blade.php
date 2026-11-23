@extends('gestion.contenido.base')
@section('contenido-gestion')
    <h3>Módulo {{ $modulo->orden }} · {{ $modulo->titulo }}</h3>
    <p class="contenido-descripcion">{{ $modulo->descripcion }}</p>
    <div class="gestion-toolbar">
        <a class="btn btn-outline" href="{{ route('gestion.contenido.curso', [$institucion->id, $curso->id]) }}">Volver a los módulos</a>
        @unless ($protegido)<a class="btn btn-primary" href="{{ route('gestion.contenido.material.create', [$institucion->id, $curso->id, $modulo->id]) }}"><x-icon name="plus" :size="18" />Crear material</a>@endunless
    </div>
    <form method="GET" class="gestion-filtros">
        <div><label for="q">Buscar material</label><input id="q" name="q" maxlength="180" value="{{ request('q') }}"></div>
        <div><label for="publicado">Publicación</label><select id="publicado" name="publicado"><option value="">Todos</option><option value="1" @selected(request('publicado') === '1')>Publicado</option><option value="0" @selected(request('publicado') === '0')>Borrador</option></select></div>
        <button class="btn btn-outline" type="submit">Filtrar</button>
        <a class="btn btn-outline btn-limpiar" href="{{ route('gestion.contenido.modulo.show', [$institucion->id, $curso->id, $modulo->id]) }}"><x-icon name="broom" :size="18" />Limpiar</a>
    </form>
    <ul class="gestion-lista">
        @forelse ($materiales as $material)
            <li class="gestion-registro">
                <div class="gestion-registro-cabecera"><h4>{{ $material->orden }}. {{ $material->titulo }}</h4><span class="gestion-estado" data-estado="{{ $material->publicado ? 'publicado' : 'borrador' }}">{{ $material->publicado ? 'Publicado' : 'Borrador' }}</span></div>
                <p>{{ \App\Services\ContenidoCursoService::TIPOS[$material->tipo] ?? 'Tipo histórico, solo consulta' }}</p>
                <div class="gestion-acciones">
                    <a class="btn btn-outline" href="{{ route('gestion.contenido.material.show', [$institucion->id, $curso->id, $modulo->id, $material->id]) }}"><x-icon name="eye" :size="18" />Revisar material<span class="sr-only"> {{ $material->titulo }}</span></a>
                    @if (! $protegido && ! $material->publicado && $material->ruta_archivo === null)<a class="btn btn-primary" href="{{ route('gestion.contenido.material.edit', [$institucion->id, $curso->id, $modulo->id, $material->id]) }}"><x-icon name="edit" :size="18" />Editar<span class="sr-only"> {{ $material->titulo }}</span></a>@endif
                </div>
            </li>
        @empty
            <li class="gestion-registro">No hay materiales que mostrar. Los borradores solo serán visibles para los participantes después de publicarlos.</li>
        @endforelse
    </ul>
    {{ $materiales->links() }}
@endsection
