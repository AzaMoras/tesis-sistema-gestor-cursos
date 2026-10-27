@extends('layouts.portal-privado')

@section('titulo_panel', 'Mi aprendizaje')
@section('subtitulo_panel', 'Portal de estudiantes')

@section('menu_panel')
    <nav class="sidebar-menu" aria-label="Menú del estudiante">
        <a class="side-link is-active" href="{{ url('/_demo-tesis/estudiante') }}"><x-icon name="grid" :size="18" /> Mi panel</a>
        <a class="side-link" href="{{ url('/_demo-tesis/cursos') }}"><x-icon name="book" :size="18" /> Explorar cursos</a>
        <a class="side-link" href="#mis-cursos"><x-icon name="layers" :size="18" /> Mis cursos</a>
        <a class="side-link" href="#proximas-actividades"><x-icon name="calendar" :size="18" /> Actividades</a>
        <a class="side-link" href="#constancias"><x-icon name="award" :size="18" /> Constancias</a>
    </nav>
@endsection

@section('contenido_privado')
    <section class="panel-welcome">
        <div>
            <p class="eyebrow">Tu espacio de aprendizaje</p>
            <h2>Aprende a tu ritmo, avanza con acompañamiento.</h2>
            <p class="text-muted">Materiales, actividades, asesorías y progreso en un solo lugar.</p>
        </div>
        <span class="round-illustration" aria-hidden="true"><x-icon name="graduation" :size="34" /></span>
    </section>
    @yield('contenido_estudiante')
@endsection
