@extends('layouts.sitio-publico')

@section('titulo', 'Iniciar sesión')

@push('estilos')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('contenido')
    <section class="container auth-section" aria-labelledby="login-titulo">
        <div class="auth-card">
            <p class="eyebrow">Aula Verde</p>
            <h1 id="login-titulo">Iniciar sesión</h1>
            <p class="text-muted">Accede con el correo y la contraseña de tu cuenta.</p>
            <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                @csrf
                @if ($errors->any())
                    <div class="auth-errors" role="alert">
                        <p>No pudimos iniciar sesión. Revisa lo siguiente:</p>
                        <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                <div>
                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="255" required @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    @error('email')<p id="email-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password">Contraseña</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" maxlength="1024" required @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    @error('password')<p id="password-error">{{ $message }}</p>@enderror
                </div>
                <button class="btn btn-primary" type="submit">Iniciar sesión</button>
            </form>
            <p>¿Aún no tienes cuenta? <a href="{{ route('register') }}">Crea tu cuenta de participante</a>.</p>
        </div>
    </section>
@endsection
