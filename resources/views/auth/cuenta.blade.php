@extends('layouts.sitio-publico')
@inject('oferta', 'App\Services\GestionOfertaService')

@section('titulo', 'Mi cuenta')

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('contenido')
    <section class="container auth-section" aria-labelledby="cuenta-titulo">
        <div class="auth-card">
            <p class="eyebrow">Sesión iniciada</p>
            <h1 id="cuenta-titulo">Hola, {{ auth('web')->user()->name }}</h1>
            <p>Tu sesión está activa. Las opciones disponibles dependen de tus permisos institucionales.</p>
            @if (config('auth.verification.enabled') && auth('web')->user()->email_verified_at === null)
                <p>Tu correo está pendiente de verificación. Verifícalo antes de acceder a las funciones académicas.</p>
                <p><a href="{{ route('verification.notice') }}">Verificar mi correo</a></p>
            @elseif (auth('web')->user()->email_verified_at !== null)
                <p>Tu correo electrónico está verificado.</p>
            @endif
            <div class="button-row"><a class="btn btn-outline" href="{{ route('inicio') }}">Volver al inicio</a></div>
            @if (auth('web')->user()->participante)
                <div class="button-row"><a class="btn btn-primary" href="{{ route('mis-cursos') }}"><x-icon name="book" :size="18" />Mis cursos</a></div>
            @endif
            @if ($oferta->institucionesAutorizadas(auth('web')->user())->isNotEmpty())
                <p><a class="btn btn-primary" href="{{ route('gestion.instituciones') }}"><x-icon name="grid" :size="18" />Panel de gestión</a></p>
            @endif
        </div>
    </section>
@endsection
