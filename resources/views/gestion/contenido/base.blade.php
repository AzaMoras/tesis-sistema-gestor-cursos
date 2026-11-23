@extends('gestion.base')
@section('encabezado', 'Contenido del curso')
@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/aula.css') }}">
@endpush
@section('gestion')
    @isset($curso)
        <div class="contenido-contexto">
            <span class="tag">{{ ucfirst($curso->estado) }}</span>
            <h2>{{ $curso->titulo }}</h2>
            <p>Organiza módulos y recursos educativos. Los materiales se publican por separado.</p>
        </div>
        @if ($protegido)
            <p class="gestion-aviso" role="status">Este curso tiene evidencias académicas o constancias. Puedes consultar su contenido; las modificaciones requieren una política de versiones todavía pendiente.</p>
        @endif
    @endisset
    @yield('contenido-gestion')
@endsection
