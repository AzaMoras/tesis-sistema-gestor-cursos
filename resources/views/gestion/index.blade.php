@extends('gestion.base')
@section('encabezado', ['categorias' => 'Categorías', 'cursos' => 'Cursos', 'ediciones' => 'Ediciones', 'grupos' => 'Grupos'][$tipo])
@section('gestion')
    @php($singular = ['categorias' => 'categoría', 'cursos' => 'curso', 'ediciones' => 'edición', 'grupos' => 'grupo'][$tipo])
    <div class="gestion-toolbar">
        <p class="text-muted">{{ $registros->total() }} {{ $registros->total() === 1 ? 'registro' : 'registros' }} en esta institución</p>
        <a class="btn btn-primary" data-gestion-crear href="{{ route('gestion.'.$tipo.'.create', $institucion->id) }}"><x-icon name="plus" :size="18" />Crear {{ $singular }}</a>
    </div>
    <form method="GET" class="gestion-filtros">
        <div><label for="q">Buscar por nombre o clave</label><input id="q" name="q" value="{{ request('q') }}" maxlength="180"></div>
        <div><label for="estado">Estado</label><select id="estado" name="estado">
            <option value="">Todos</option>
            @foreach (['activa', 'borrador', 'publicado', 'abierta'] as $estado)<option @selected(request('estado') === $estado)>{{ $estado }}</option>@endforeach
        </select></div>
        <button type="submit" class="btn btn-outline">Filtrar</button>
        <a href="{{ route('gestion.'.$tipo.'.index', $institucion->id) }}">Limpiar</a>
    </form>
    <ul class="gestion-lista">
        @forelse ($registros as $registro)
            <li class="surface-card gestion-registro">
                @php($nombreRegistro = $registro->nombre ?? $registro->titulo ?? $registro->clave ?? $registro->codigo)
                <div class="gestion-registro-cabecera">
                    <h2>{{ $nombreRegistro }}</h2>
                    <span class="gestion-estado" data-estado="{{ $registro->estado }}">{{ ucfirst($registro->estado) }}</span>
                </div>
                <div class="gestion-secundaria">
                @if ($tipo === 'categorias')<p>{{ $registro->descripcion ?: 'Sin descripción adicional.' }}</p>@endif
                @if ($tipo === 'cursos')<p>Categoría: {{ $registro->categoria->nombre }} · {{ \Illuminate\Support\Str::limit($registro->descripcion, 220) }}</p>@endif
                @if ($tipo === 'ediciones')
                    <p>Curso: {{ $registro->curso->titulo }} · {{ $registro->modalidad === 'autogestionado' ? 'Autogestionado virtual' : 'Acompañado virtual' }}</p>
                    <p>{{ $registro->fecha_inicio->format('d/m/Y') }} a {{ $registro->fecha_fin->format('d/m/Y') }} · {{ $registro->precio }} {{ $registro->moneda }}</p>
                @endif
                @if ($tipo === 'grupos')<p>Curso: {{ $registro->edicion->curso->titulo }} · Edición: {{ $registro->edicion->clave }} · Cupo: {{ $registro->cupo_maximo ?? 'Sin definir' }}</p>@endif
                <p class="gestion-identificador">Identificador: {{ $registro->id }}</p>
                </div>
                <div class="gestion-acciones" role="group" aria-label="Acciones de {{ $nombreRegistro }}">
                    @if ($tipo === 'cursos')
                        <a class="btn btn-outline" href="{{ route('gestion.contenido.curso', [$institucion->id, $registro->id]) }}"><x-icon name="layers" :size="18" />Contenido del curso</a>
                    @endif
                    <a class="btn btn-outline" href="{{ route('gestion.'.$tipo.'.show', [$institucion->id, $registro->id]) }}" aria-label="Ver detalles de {{ $nombreRegistro }}"><x-icon name="eye" :size="18" />Ver detalles</a>
                    @if ($oferta->editable($tipo, $registro))
                        <a class="btn btn-primary" href="{{ route('gestion.'.$tipo.'.edit', [$institucion->id, $registro->id]) }}" aria-label="Editar {{ $nombreRegistro }}"><x-icon name="edit" :size="18" />Editar</a>
                    @endif
                    @if ($tipo !== 'categorias' && $registro->estado === 'borrador')
                        <form method="POST" action="{{ route('gestion.'.$tipo.'.publish', [$institucion->id, $registro->id]) }}">
                            @csrf
                            <button class="btn btn-outline" type="submit"><x-icon name="check" :size="18" />{{ ['cursos' => 'Publicar curso', 'ediciones' => 'Abrir edición', 'grupos' => 'Abrir grupo'][$tipo] }}</button>
                        </form>
                    @endif
                </div>
            </li>
        @empty
            <li>No hay registros que coincidan. Puedes crear uno o cambiar los filtros.</li>
        @endforelse
    </ul>
    {{ $registros->links() }}
@endsection
