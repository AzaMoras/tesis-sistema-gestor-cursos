<footer class="public-footer">
    <div class="container footer-grid">
        <div>
            <a class="brand" href="{{ url('/') }}"><span class="brand-mark" aria-hidden="true"><x-icon name="book" :size="18" /></span> Aula Verde</a>
            <p>Plataforma de gestión de cursos y aprendizaje virtual para instituciones.</p>
        </div>
        <div>
            <h3>Explorar</h3>
            <a href="{{ url('/_demo-tesis/cursos') }}">Catálogo de cursos</a>
            <a href="{{ url('/_demo-tesis') }}#como-funciona">Cómo funciona</a>
        </div>
        <div>
            <h3>Información</h3>
            <p>Diseño de demostración · los trámites reales dependerán de la implementación.</p>
        </div>
    </div>
    <div class="container footer-bottom">© {{ date('Y') }} Aula Verde · Prototipo académico.</div>
</footer>
