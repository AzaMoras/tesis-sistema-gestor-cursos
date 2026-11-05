<footer class="public-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand" href="{{ route('inicio') }}"><span class="brand-mark" aria-hidden="true"><x-icon name="book" :size="18" /></span> Aula Verde</a>
            <p>Sistema de gestión de cursos e inscripciones para formación 100 % virtual.</p>
        </div>
        <div><h3>Explorar</h3><a href="{{ route('inicio') }}#cursos">Información de cursos</a><a href="{{ route('inicio') }}#como-funciona">Cómo funciona</a></div>
        <div><h3>Información</h3><a href="{{ route('inicio') }}#contacto">Contacto</a><p>Proyecto académico en desarrollo. Catálogo y solicitudes pendientes de habilitación.</p></div>
    </div>
    <div class="container footer-bottom">© {{ date('Y') }} Aula Verde · Proyecto académico.</div>
</footer>
