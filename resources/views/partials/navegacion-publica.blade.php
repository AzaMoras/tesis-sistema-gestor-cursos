<header class="public-header">
    <nav class="container public-nav" aria-label="Navegación principal">
        <a class="brand" href="{{ route('inicio') }}"><x-marca-aula-verde /></a>
        <div class="desktop-links">
            <a href="{{ route('inicio') }}">Inicio</a>
            <a href="{{ route('cursos.index') }}">Cursos</a>
            <a href="{{ route('inicio') }}#como-funciona">Cómo funciona</a>
            <a href="{{ route('inicio') }}#contacto">Contacto</a>
        </div>
        <div class="header-actions">
            <x-acceso-publico :curso="request()->routeIs('cursos.show') ? (int) request()->route('curso') : null" />
            <button class="btn btn-outline btn-sm theme-toggle" type="button" data-theme-toggle aria-label="Activar modo oscuro" aria-pressed="false" hidden><x-icon name="theme" :size="19" /><span data-theme-label>Modo oscuro</span></button>
        </div>
        <details class="public-mobile-menu">
            <summary aria-label="Mostrar navegación"><x-icon name="menu" :size="22" /></summary>
            <div class="mobile-links">
                <a href="{{ route('inicio') }}">Inicio</a>
                <a href="{{ route('cursos.index') }}">Cursos</a>
                <a href="{{ route('inicio') }}#como-funciona">Cómo funciona</a>
                <a href="{{ route('inicio') }}#contacto">Contacto</a>
            </div>
        </details>
    </nav>
</header>
