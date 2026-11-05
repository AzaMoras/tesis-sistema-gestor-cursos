@extends('layouts.sitio-publico')

@section('titulo', 'Formación 100 % virtual')

@section('contenido')
    <section class="hero-section" aria-labelledby="presentacion-titulo">
        <div class="container hero-grid">
            <div>
                <p class="eyebrow"><span class="eyebrow-line"></span> FORMACIÓN 100 % VIRTUAL</p>
                <h1 id="presentacion-titulo">Aprende algo nuevo.<br><em>Abre tu próximo camino.</em></h1>
                <p class="hero-lead">Da espacio a lo que quieres aprender, desde donde estés. Conoce las opciones de formación virtual y elige entre estudiar de forma autogestionada o con la orientación de un instructor.</p>
                <div class="button-row">
                    <a class="btn btn-primary" href="#cursos">Descubre cómo aprender <x-icon name="arrow-right" :size="18" /></a>
                    <a class="btn btn-outline" href="#como-funciona">Conoce los pasos</a>
                </div>
                <div class="hero-mini-stats"><span><x-icon name="check" :size="16" /> Sin traslados</span><span><x-icon name="check" :size="16" /> Formación en línea</span></div>
            </div>
            <aside class="hero-preview learning-overview" aria-labelledby="opciones-titulo">
                <img class="hero-study-image" src="{{ asset('images/estudio-virtual.jpg') }}" alt="" width="1200" height="720" fetchpriority="high">
                <p class="eyebrow">DOS FORMAS DE APRENDER</p>
                <h2 id="opciones-titulo">Elige cómo avanzar</h2>
                <div class="preview-tile"><x-icon name="book" :size="23" /><div><h3>Aprendizaje autogestionado</h3><p>Organiza tu estudio según las fechas y condiciones del curso.</p></div></div>
                <div class="preview-tile"><x-icon name="users" :size="23" /><div><h3>Con instructor</h3><p>Recibe orientación y participa en sesiones virtuales cuando el programa lo contemple.</p></div></div>
                <p class="overview-note">Ambas opciones son exclusivamente virtuales.</p>
            </aside>
        </div>
    </section>

    <section class="container section-space" id="beneficios" aria-labelledby="beneficios-titulo">
        <div class="section-heading"><p class="eyebrow">APRENDER DESDE DONDE ESTÉS</p><h2 id="beneficios-titulo">Más posibilidades para tu formación</h2><p>La capacitación virtual conecta el aprendizaje con tu vida cotidiana.</p></div>
        <div class="feature-grid">
            <x-beneficio icono="home" titulo="Sin desplazamientos">Accede a la formación desde un lugar con conexión a internet y un dispositivo compatible.</x-beneficio>
            <x-beneficio icono="clock" titulo="Organización de tu tiempo">Planifica tu estudio considerando los plazos y, si corresponde, los horarios de las sesiones virtuales.</x-beneficio>
            <x-beneficio icono="layers" titulo="Un recorrido claro">Conoce los contenidos, requisitos y criterios de evaluación antes de elegir tu formación.</x-beneficio>
        </div>
    </section>

    <section class="process-section" id="como-funciona" aria-labelledby="proceso-titulo">
        <div class="container section-space">
            <div class="section-heading"><p class="eyebrow">PASO A PASO</p><h2 id="proceso-titulo">Tu próximo paso empieza aquí</h2><p>Cuando se abra la oferta de cursos, este será tu recorrido. El catálogo y las solicitudes todavía no están disponibles.</p></div>
            <ol class="feature-grid process-list">
                <li class="feature-card"><h3>Consulta la oferta</h3><p>Revisa objetivos, contenidos, requisitos y fechas de cada curso cuando se publique el catálogo.</p></li>
                <li class="feature-card"><h3>Solicita tu acceso</h3><p>Envía tu solicitud cuando se habilite este servicio. El personal autorizado revisará tus requisitos antes de confirmar el acceso.</p></li>
                <li class="feature-card"><h3>Comienza tu formación</h3><p>Con la inscripción confirmada, sigue las actividades y evaluaciones definidas para tu curso virtual.</p></li>
            </ol>
        </div>
    </section>

    <section class="container section-space" id="cursos" aria-labelledby="cursos-titulo">
        <div class="section-heading"><p class="eyebrow">TU SIGUIENTE OPORTUNIDAD</p><h2 id="cursos-titulo">Encuentra tu forma de aprender</h2><p>Conoce qué aprenderás, el acompañamiento que recibirás y cuánto tiempo necesitarás. Cada programa indicará sus requisitos, fechas y condiciones de inscripción.</p></div>
        <x-catalogo-pendiente />
    </section>

    <section class="container cta-section" id="contacto" aria-labelledby="contacto-titulo">
        <div><p class="eyebrow">CONTACTO</p><h2 id="contacto-titulo">¿Tienes dudas sobre tu próximo paso?</h2><p>Los canales oficiales de contacto se publicarán cuando la institución los confirme. Mientras tanto, conoce los pasos previstos para comenzar. Las solicitudes de acceso todavía no están disponibles.</p></div>
        <a class="btn btn-outline" href="#como-funciona">Consultar el proceso <x-icon name="arrow-right" :size="18" /></a>
    </section>
@endsection
