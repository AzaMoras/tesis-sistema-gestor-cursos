@extends('gestion.base')
@section('encabezado', 'Selecciona una institución')
@section('gestion')
    <ul class="gestion-lista gestion-instituciones">
        @forelse ($instituciones as $opcion)
            <li>
                <a class="gestion-institucion" href="{{ route('gestion.cursos.index', $opcion->id) }}">
                    <span class="icon-bubble"><x-icon name="institution" :size="22" /></span>
                    <span class="gestion-institucion-texto">
                        <strong>{{ $opcion->nombre }}</strong>
                        <span>Seleccionar institución</span>
                    </span>
                    <span class="gestion-institucion-flecha"><x-icon name="arrow-right" :size="20" /></span>
                </a>
            </li>
        @empty
            <li>No tienes instituciones con permisos de gestión de cursos. Solicita la asignación al personal autorizado.</li>
        @endforelse
    </ul>
    <a class="btn btn-outline gestion-volver-cuenta" href="{{ route('cuenta') }}"><x-icon name="arrow-left" :size="18" />Volver a mi cuenta</a>
@endsection
