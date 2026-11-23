@extends('layouts.sitio-publico')

@section('titulo', 'Gestión de oferta virtual')
@section('clase_cuerpo', 'sitio-publico pagina-gestion')

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/gestion.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/gestion.js') }}" defer></script>
@endpush

@section('contenido')
    <section class="container gestion" aria-labelledby="gestion-titulo">
        <p class="eyebrow">Gestión institucional · 100 % virtual</p>
        <h1 id="gestion-titulo">@yield('encabezado', 'Oferta de cursos')</h1>
        @isset($institucion)
            <p>{{ $institucion->nombre }}</p>
            <nav class="gestion-nav" aria-label="Gestión de oferta">
                <a href="{{ route('gestion.instituciones') }}">Cambiar institución</a>
                @foreach (['categorias' => 'Categorías', 'cursos' => 'Cursos', 'ediciones' => 'Ediciones', 'grupos' => 'Grupos'] as $seccion => $nombre)
                    <a href="{{ route('gestion.'.$seccion.'.index', $institucion->id) }}" @if (($tipo ?? '') === $seccion) aria-current="page" @endif>{{ $nombre }}</a>
                @endforeach
                <a href="{{ route('gestion.contenido.index', $institucion->id) }}" @if (request()->routeIs('gestion.contenido.*')) aria-current="page" @endif>Contenido del curso</a>
            </nav>
        @endisset
        @if ($errors->any())
            <div role="alert" class="auth-errors"><p>Revisa los datos:</p><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('gestion')
    </section>
@endsection
