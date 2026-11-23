@extends('layouts.sitio-publico')

@section('titulo', 'Mi cuenta')

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('contenido')
    <section class="container auth-section" aria-labelledby="cuenta-titulo">
        <div class="auth-card">
            <p class="eyebrow">Sesión iniciada</p>
            <h1 id="cuenta-titulo">Hola, {{ $usuario->name }}</h1>
            @if (config('auth.verification.enabled') && ! $usuario->hasVerifiedEmail())
                <p>Tu correo está pendiente de verificación. Verifícalo antes de acceder a las funciones académicas.</p>
                <p><a href="{{ route('verification.notice') }}">Verificar mi correo</a></p>
            @elseif ($usuario->hasVerifiedEmail())
                <p>Tu correo electrónico está verificado.</p>
            @endif
            @if ($mostrarMisCursos || $puedeGestionar)
                <div class="cuenta-contextos">
                    @if ($mostrarMisCursos)
                        <div class="cuenta-contexto">
                            @if ($puedeGestionar)
                                <h2>Como participante</h2>
                            @endif
                            <a class="btn btn-primary" href="{{ route('mis-cursos') }}"><x-icon name="book" :size="18" />Mis cursos</a>
                        </div>
                    @endif
                    @if ($puedeGestionar)
                        <div class="cuenta-contexto">
                            @if ($mostrarMisCursos)
                                <h2>Como personal de gestión</h2>
                            @endif
                            <a class="btn btn-primary cuenta-panel" href="{{ route('gestion.instituciones') }}"><x-icon name="grid" :size="18" />Panel de gestión</a>
                        </div>
                    @endif
                </div>
            @endif
            <div class="cuenta-volver"><a class="btn btn-outline" href="{{ route('inicio') }}">Volver al inicio</a></div>
        </div>
    </section>
@endsection
