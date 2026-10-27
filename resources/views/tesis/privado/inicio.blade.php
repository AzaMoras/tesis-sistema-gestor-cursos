@extends('layouts.portal-privado')

@section('titulo', 'Administración académica')
@section('titulo_panel', 'Resumen de gestión')
@section('subtitulo_panel', 'Portal privado · vista base')

@section('contenido_privado')
    <section class="panel-welcome"><div><p class="eyebrow">OPERACIÓN ACADÉMICA</p><h2>Todo tu equipo, en un mismo espacio.</h2><p class="text-muted">Una misma estructura visual con acciones que dependen de roles y permisos.</p></div><span class="round-illustration" aria-hidden="true"><x-icon name="grid" :size="32" /></span></section>
    <div class="metrics-grid" id="modulos">
        <article class="metric-card"><span>Cursos publicados</span><strong>12</strong><small>Dato ilustrativo</small></article>
        <article class="metric-card"><span>Solicitudes pendientes</span><strong>8</strong><small>Dato ilustrativo</small></article>
        <article class="metric-card"><span>Inscripciones confirmadas</span><strong>146</strong><small>Dato ilustrativo</small></article>
    </div>
    <section class="surface-card" id="gestion"><div class="card-heading"><div><p class="eyebrow">MÓDULOS CONECTADOS</p><h2>Áreas del sistema</h2></div><span class="tag tag-soft">En diseño</span></div>
        <div class="feature-grid feature-grid-compact">
            <div class="feature-card"><span class="icon-bubble"><x-icon name="book" :size="22" /></span><h3>Cursos y grupos</h3><p>Ediciones, módulos, sesiones y asignación de instructores.</p></div>
            <div class="feature-card"><span class="icon-bubble"><x-icon name="users" :size="22" /></span><h3>Inscripciones</h3><p>Requisitos, solicitudes, lista de espera y pagos.</p></div>
            <div class="feature-card" id="seguridad"><span class="icon-bubble"><x-icon name="shield" :size="22" /></span><h3>Permisos</h3><p>Acceso mediante roles, policies y autorización por institución.</p></div>
        </div>
    </section>
    <p class="demo-note">Prototipo visual. La autorización, consultas, reportes y operaciones reales se implementarán en Laravel.</p>
@endsection
