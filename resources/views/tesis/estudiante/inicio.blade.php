@extends('layouts.portal-estudiante')

@section('titulo', 'Mi aprendizaje')

@section('contenido_estudiante')
    {{-- Datos ilustrativos. La información real se obtendrá del controlador y policies. --}}
    <div class="metrics-grid" id="mis-cursos">
        <article class="metric-card"><span>Cursos activos</span><strong>2</strong><small>Aprendizaje en progreso</small></article>
        <article class="metric-card"><span>Actividades pendientes</span><strong>3</strong><small>Revisa las fechas límite</small></article>
        <article class="metric-card"><span>Constancias disponibles</span><strong>1</strong><small>Con criterios aprobados</small></article>
    </div>
    <div class="two-column-panels">
        <section class="surface-card">
            <div class="card-heading"><div><p class="eyebrow">CONTINÚA APRENDIENDO</p><h2>Mis cursos</h2></div><span class="tag tag-soft">Ejemplo</span></div>
            <div class="learning-row"><span class="learning-icon"><x-icon name="book" :size="21" /></span><div class="learning-info"><strong>Programación web con Laravel</strong><p>Módulo 3 · Bases de datos</p><div class="progress-track"><span style="width:64%"></span></div></div><b>64 %</b></div>
            <div class="learning-row"><span class="learning-icon"><x-icon name="book" :size="21" /></span><div class="learning-info"><strong>Excel para el trabajo</strong><p>Módulo 2 · Funciones</p><div class="progress-track"><span style="width:35%"></span></div></div><b>35 %</b></div>
        </section>
        <section class="surface-card" id="proximas-actividades">
            <div class="card-heading"><div><p class="eyebrow">ORGANIZA TU SEMANA</p><h2>Próximas actividades</h2></div></div>
            <div class="task-row"><x-icon name="file" :size="20" /><div><strong>Entrega: práctica de rutas</strong><p>Actividad de aprendizaje</p></div><span class="status-chip">Pendiente</span></div>
            <div class="task-row"><x-icon name="calendar" :size="20" /><div><strong>Asesoría virtual</strong><p>Sesión programada</p></div><span class="status-chip">Próxima</span></div>
        </section>
    </div>
    <section class="surface-card" id="constancias"><div class="card-heading"><div><p class="eyebrow">RECONOCE TU ESFUERZO</p><h2>Mis constancias</h2></div></div><p class="text-muted">Cuando apruebes un curso, aquí podrás consultar la constancia y su código de verificación. El PDF real se generará desde Laravel.</p></section>
@endsection
