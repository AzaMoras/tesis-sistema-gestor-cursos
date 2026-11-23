@extends('layouts.sitio-publico')
@section('titulo', 'Catálogo de cursos virtuales')

@section('contenido')
    <section class="catalogo-presentacion container" aria-labelledby="catalogo-titulo">
        <p class="eyebrow">APRENDE EN LÍNEA</p>
        <h1 id="catalogo-titulo">Explora tu próximo curso</h1>
        <p>Consulta programas publicados, sus objetivos y las condiciones de cada edición. Toda la formación es virtual.</p>
    </section>
    <section class="container catalogo-contenido" aria-labelledby="resultados-titulo">
        <form class="catalogo-filtros" action="{{ route('cursos.index') }}" method="GET" aria-label="Buscar y filtrar cursos">
            <div><label for="buscar-curso">Nombre del curso</label><input type="search" id="buscar-curso" name="q" maxlength="180" value="{{ $filtros['q'] ?? '' }}" placeholder="¿Qué quieres aprender?"></div>
            <div><label for="categoria">Categoría</label><select id="categoria" name="categoria"><option value="">Todas las categorías</option>
                @foreach ($categorias as $categoria)
                    <option value="{{ $categoria->id }}" @selected(($filtros['categoria'] ?? '') == $categoria->id)>{{ $categoria->nombre }} · {{ $categoria->institucion->nombre }}</option>
                @endforeach
            </select></div>
            <div><label for="modalidad">Modalidad virtual</label><select id="modalidad" name="modalidad"><option value="">Todas las modalidades</option>
                @foreach ($modalidades as $clave => $nombre)
                    <option value="{{ $clave }}" @selected(($filtros['modalidad'] ?? '') === $clave)>{{ $nombre }}</option>
                @endforeach
            </select></div>
            <button class="btn btn-primary" type="submit">Buscar cursos</button>
            <a class="btn btn-outline btn-limpiar" href="{{ route('cursos.index') }}"><x-icon name="broom" :size="18" />Limpiar filtros</a>
        </form>
        @if ($errors->any())
            <div class="catalogo-aviso" role="alert"><p>Revisa los filtros:</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <div class="results-heading"><h2 id="resultados-titulo">Cursos publicados</h2><p class="text-muted">{{ $cursos->total() }} {{ $cursos->total() === 1 ? 'resultado' : 'resultados' }}</p></div>
        @if ($cursos->isNotEmpty())
            <div class="catalogo-tarjetas">@foreach ($cursos as $curso)<x-curso-publico :curso="$curso" />@endforeach</div>
        @else
            <div class="surface-card catalog-empty"><span class="icon-bubble"><x-icon name="book" :size="26" /></span><div><h3>No hay cursos para mostrar</h3><p>No encontramos cursos publicados con estos filtros. Puedes cambiar la búsqueda o volver más adelante para conocer nuevas publicaciones.</p></div></div>
        @endif
        @if ($cursos->hasPages())
            <nav class="catalogo-paginacion" aria-label="Paginación de cursos">
                @if ($cursos->onFirstPage())<span>Anterior</span>@else<a class="btn btn-outline" href="{{ $cursos->previousPageUrl() }}" rel="prev">Anterior</a>@endif
                <span>Página {{ $cursos->currentPage() }} de {{ $cursos->lastPage() }}</span>
                @if ($cursos->hasMorePages())<a class="btn btn-outline" href="{{ $cursos->nextPageUrl() }}" rel="next">Siguiente</a>@else<span>Siguiente</span>@endif
            </nav>
        @endif
    </section>
@endsection
