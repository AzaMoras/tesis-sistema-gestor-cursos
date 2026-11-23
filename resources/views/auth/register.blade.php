@extends('layouts.sitio-publico')

@section('titulo', 'Crear cuenta')

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('contenido')
    <section class="container auth-section" aria-labelledby="registro-titulo">
        <div class="auth-card">
            <div class="brand auth-brand"><x-marca-aula-verde /></div>
            <h1 id="registro-titulo">Crea tu cuenta</h1>
            <p class="text-muted">Regístrate como participante. Solo necesitas tu nombre, correo y contraseña.@if (config('auth.verification.enabled')) Después verificarás tu correo electrónico.@endif</p>
            <form method="POST" action="{{ route('register.store') }}" class="auth-form">
                @csrf
                @if ($errors->any())
                    <div class="auth-errors" role="alert">
                        <p>No pudimos crear tu cuenta. Revisa lo siguiente:</p>
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <div>
                    <label for="name">Nombre</label>
                    <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" maxlength="255" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    @error('name')<p id="name-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    @error('email')<p id="email-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password">Contraseña</label>
                    <p id="password-ayuda">Usa entre 12 y 72 caracteres, con mayúsculas, minúsculas, números y símbolos. Los caracteres acentuados pueden reducir el máximo permitido.</p>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required aria-describedby="password-ayuda @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                    @error('password')<p id="password-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation">Confirmar contraseña</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" maxlength="72" required @error('password_confirmation') aria-invalid="true" aria-describedby="confirmation-error" @enderror>
                    @error('password_confirmation')<p id="confirmation-error">{{ $message }}</p>@enderror
                </div>
                <button class="btn btn-primary" type="submit">Crear cuenta</button>
            </form>
            <p>¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a>.</p>
        </div>
    </section>
@endsection
