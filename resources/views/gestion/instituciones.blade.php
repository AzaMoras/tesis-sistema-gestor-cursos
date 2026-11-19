@extends('gestion.base')
@section('encabezado', 'Selecciona una institución')
@section('gestion')
    <ul class="gestion-lista">
        @forelse ($instituciones as $opcion)
            <li class="surface-card"><a href="{{ route('gestion.cursos.index', $opcion->id) }}">{{ $opcion->nombre }}</a></li>
        @empty
            <li>No tienes instituciones con permisos de gestión de cursos. Solicita la asignación al personal autorizado.</li>
        @endforelse
    </ul>
    <a href="{{ route('cuenta') }}">Volver a mi cuenta</a>
@endsection
