@extends('layouts.sitio-publico')

@section('titulo', 'Aprende en línea')

@section('contenido')
    <section class="hero-section">
        <div class="container hero-grid">
            <div>
                <p class="eyebrow"><span class="eyebrow-line"></span> UNA NUEVA FORMA DE APRENDER</p>
                <h1>Aprende a tu ritmo.<br><em>Avanza con acompañamiento.</em></h1>
                <p class="hero-lead">Descubre cursos en línea con contenido organizado, actividades, evaluaciones y orientación de especialistas.</p>
                <div class="button-row">
                    <a class="btn btn-primary" href="{{ url('/_demo-tesis/cursos') }}">Explorar cursos <x-icon name="arrow-right" :size="18" /></a>
                    <a class="btn btn-outline" href="#como-funciona">Cómo funciona</a>
                </div>
                <div class="hero-mini-stats">
                    <span><x-icon name="check" :size="16" /> 100 % en línea</span>
                    <span><x-icon name="check" :size="16" /> Avanza por módulos</span>
                </div>
            </div>
            <div class="hero-preview" aria-label="Vista ilustrativa del panel de aprendizaje">
                <div class="preview-top"><span class="preview-dot"></span><span class="preview-dot"></span><span class="preview-dot"></span><span>Vista de aprendizaje</span></div>
                <div class="preview-course">
                    <p class="eyebrow">Curso en progreso</p>
                    <h3>Diseño web desde cero</h3>
                    <p>Unidad 3 de 5 · Fundamentos prácticos</p>
                    <div class="progress-track"><span style="width:64%"></span></div>
                    <div class="preview-stats"><span>64 % completado</span><span>Continuar curso <x-icon name="arrow-right" :size="15" /></span></div>
                </div>
                <div class="preview-tile"><x-icon name="play" :size="20" /><div><strong>Clases y materiales</strong><span>Lecciones organizadas paso a paso</span></div></div>
                <div class="preview-tile"><x-icon name="award" :size="20" /><div><strong>Constancias verificables</strong><span>Tras cumplir criterios académicos</span></div></div>
            </div>
        </div>
    </section>
    <section class="container section-space" id="como-funciona">
        <div class="section-heading"><p class="eyebrow">APRENDIZAJE CON PROPÓSITO</p><h2>Todo lo que necesitas, en un mismo lugar</h2><p>La plataforma combina recursos digitales con seguimiento académico y una gestión clara de tus avances.</p></div>
        <div class="feature-grid">
            <article class="feature-card"><span class="icon-bubble"><x-icon name="book" :size="23" /></span><h3>Contenido estructurado</h3><p>Consulta los módulos y materiales de cada curso desde una sola interfaz.</p></article>
            <article class="feature-card"><span class="icon-bubble"><x-icon name="users" :size="23" /></span><h3>Acompañamiento virtual</h3><p>Participa en sesiones y recibe retroalimentación cuando tu curso lo contemple.</p></article>
            <article class="feature-card"><span class="icon-bubble"><x-icon name="award" :size="23" /></span><h3>Progreso verificable</h3><p>Registra entregas, evaluaciones y constancias conforme a los criterios académicos.</p></article>
        </div>
    </section>
    <section class="container cta-section" id="solicitar-acceso">
        <div><p class="eyebrow">REGISTRO CONTROLADO</p><h2>Solicita acceso a una institución</h2><p>Envía tus datos para revisión. Una solicitud no crea automáticamente una cuenta.</p></div>
        <span class="status-chip">Formulario de solicitud: pendiente de implementar</span>
    </section>
@endsection
