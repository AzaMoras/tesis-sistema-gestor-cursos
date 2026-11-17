@extends('layouts.sitio-publico')

@section('titulo', 'Mi cuenta')

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('contenido')
    <section class="container auth-section" aria-labelledby="cuenta-titulo">
        <div class="auth-card">
            <p class="eyebrow">Sesión iniciada</p>
            <h1 id="cuenta-titulo">Hola, {{ auth('web')->user()->name }}</h1>
            <p>Tu sesión está activa. Las funciones privadas de cursos aún no están disponibles.</p>
            <p>No se ha asignado ninguna institución ni rol al iniciar sesión.</p>
            @if (auth('web')->user()->email_verified_at === null)
                <p>Tu correo está pendiente de verificación. El envío de verificación aún no está disponible; las funciones privadas de participante siguen pendientes.</p>
            @endif
            <div class="button-row"><a class="btn btn-outline" href="{{ route('inicio') }}">Volver al inicio</a></div>
        </div>
    </section>
@endsection
