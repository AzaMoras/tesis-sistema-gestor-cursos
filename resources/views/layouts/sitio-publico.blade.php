@extends('layouts.app-base')

@section('clase_cuerpo', 'sitio-publico')

@push('scripts_cabecera')
    <script src="{{ asset('js/tema-publico.js') }}"></script>
@endpush

@section('estructura')
    <a class="skip-link" href="#contenido-principal">Saltar al contenido</a>
    @include('partials.navegacion-publica')
    <main id="contenido-principal" tabindex="-1">
        @yield('contenido')
    </main>
    @include('partials.pie-publico')
@endsection
