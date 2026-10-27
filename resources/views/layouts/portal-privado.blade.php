@extends('layouts.app-base')

@section('estructura')
    <a class="skip-link" href="#contenido-panel">Saltar al panel</a>
    <div class="app-shell">
        <aside class="app-sidebar" aria-label="Navegación privada">
            <a class="brand brand-light" href="{{ url('/') }}">
                <span class="brand-mark" aria-hidden="true"><x-icon name="book" :size="19" /></span>
                <span>Aula Verde</span>
            </a>
            <p class="sidebar-caption">ESPACIO DE TRABAJO</p>
            @hasSection('menu_panel')
                @yield('menu_panel')
            @else
                @include('partials.navegacion-privada')
            @endif
            <div class="sidebar-bottom">
                <a href="{{ url('/') }}" class="side-link"><x-icon name="home" :size="18" /> Sitio público</a>
                <p class="sidebar-note">Los enlaces y acciones finales se habilitarán según permisos de Laravel.</p>
            </div>
        </aside>
        <div class="app-workspace">
            <header class="app-topbar">
                <div class="topbar-title">
                    <p class="eyebrow">@yield('subtitulo_panel', 'Plataforma educativa')</p>
                    <h1>@yield('titulo_panel', 'Panel de control')</h1>
                </div>
                <div class="account-chip">
                    <span class="avatar" aria-hidden="true"><x-icon name="user" :size="19" /></span>
                    <span>{{ auth()->user()?->name ?? 'Usuario de demostración' }}</span>
                </div>
            </header>
            <details class="mobile-menu">
                <summary><x-icon name="menu" :size="18" /> Abrir menú</summary>
                <div class="mobile-menu-inner">
                    @hasSection('menu_panel')
                        @yield('menu_panel')
                    @else
                        @include('partials.navegacion-privada')
                    @endif
                </div>
            </details>
            <main id="contenido-panel" class="dashboard-content" tabindex="-1">
                @yield('contenido_privado')
            </main>
        </div>
    </div>
@endsection
