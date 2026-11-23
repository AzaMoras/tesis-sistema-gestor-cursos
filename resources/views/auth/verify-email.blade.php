@extends('layouts.sitio-publico')

@section('titulo', 'Verificar correo')

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('contenido')
    <section class="container auth-section" aria-labelledby="verificacion-titulo">
        <div class="auth-card">
            <div class="brand auth-brand"><x-marca-aula-verde /></div>
            <h1 id="verificacion-titulo">Verifica tu correo</h1>
            <p>Abre el enlace que enviamos a <strong>{{ auth('web')->user()->email }}</strong>. Revisa también tu carpeta de correo no deseado.</p>
            <p>Hasta verificarlo, las funciones académicas permanecerán restringidas. Si el enlace venció, solicita uno nuevo. Para abrirlo debes iniciar sesión con esta misma cuenta.</p>
            <form method="POST" action="{{ route('verification.send') }}" class="auth-form">
                @csrf
                <button class="btn btn-primary" type="submit">Reenviar correo de verificación</button>
            </form>
            <p>Puedes solicitar hasta tres reenvíos por minuto.</p>
            <div class="button-row"><a class="btn btn-outline" href="{{ route('cuenta') }}">Volver a mi cuenta</a></div>
        </div>
    </section>
@endsection
