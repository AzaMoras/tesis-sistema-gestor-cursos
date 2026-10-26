<header class="public-header">
    <nav class="container public-nav" aria-label="Navegación principal">
        <a class="brand" href="{{ url('/') }}">
            <span class="brand-mark" aria-hidden="true"><x-icon name="book" :size="19" /></span>
            <span>Aula Verde</span>
        </a>
        <div class="desktop-links">
            <a href="{{ url('/') }}">Inicio</a>
            <a href="{{ url('/_demo-tesis/cursos') }}">Cursos</a>
            <a href="{{ url('/_demo-tesis') }}#como-funciona">Cómo funciona</a>
        </div>
        <div class="desktop-links actions-right">
            @auth
                <a class="btn btn-outline btn-sm" href="{{ url('/_demo-tesis/estudiante') }}">Mi portal</a>
            @else
                <a class="btn btn-outline btn-sm" href="{{ url('/login') }}">Iniciar sesión</a>
            @endauth
            <a class="btn btn-primary btn-sm" href="{{ url('/_demo-tesis') }}#solicitar-acceso">Solicitar acceso <x-icon name="arrow-right" :size="15" /></a>
        </div>
        <details class="public-mobile-menu">
            <summary aria-label="Mostrar navegación"><x-icon name="menu" :size="22" /></summary>
            <div class="mobile-links">
                <a href="{{ url('/') }}">Inicio</a>
                <a href="{{ url('/_demo-tesis/cursos') }}">Cursos</a>
                <a href="{{ url('/login') }}">Iniciar sesión</a>
            </div>
        </details>
    </nav>
</header>
