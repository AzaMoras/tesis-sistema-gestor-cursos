@extends('layouts.portal-publico')

@section('titulo', 'Catálogo de cursos')
@section('encabezado_portal', 'Encuentra tu próximo curso')
@section('descripcion_portal', 'Explora programas de formación virtual y conoce sus modalidades, duración y requisitos.')

@section('contenido_portal')
    @php
        // Datos exclusivamente ilustrativos. Reemplazar por registros del controlador.
        $cursosDemo = [
            ['titulo' => 'Programación web con Laravel', 'categoria' => 'Tecnología', 'nivel' => 'Intermedio', 'duracion' => '8 semanas', 'modalidad' => 'Con acompañamiento', 'precio' => '$799 MXN'],
            ['titulo' => 'Excel para el trabajo', 'categoria' => 'Productividad', 'nivel' => 'Básico', 'duracion' => '4 semanas', 'modalidad' => 'Autogestionado', 'precio' => 'Gratuito'],
            ['titulo' => 'Fundamentos de análisis de datos', 'categoria' => 'Tecnología', 'nivel' => 'Básico', 'duracion' => '6 semanas', 'modalidad' => 'Con acompañamiento', 'precio' => '$499 MXN'],
        ];
    @endphp
    <div class="catalog-layout">
        <aside class="filter-panel" aria-label="Información de filtros">
            <h2>Explorar</h2>
            <p>Filtra por categoría, modalidad y nivel cuando conectemos el catálogo a Laravel.</p>
            <div class="filter-tag">Categorías</div>
            <div class="filter-options"><span>Tecnología</span><span>Productividad</span><span>Idiomas</span></div>
            <div class="filter-tag">Modalidad</div>
            <div class="filter-options"><span>Autogestionado</span><span>Con acompañamiento</span></div>
        </aside>
        <section aria-labelledby="listado-titulo">
            <div class="results-heading"><div><p class="eyebrow">PROGRAMAS DISPONIBLES</p><h2 id="listado-titulo">Cursos destacados</h2></div><span class="status-chip">Datos demostrativos</span></div>
            <div class="course-grid">
                @foreach($cursosDemo as $curso)
                    <article class="course-card">
                        <div class="course-cover"><x-icon name="book" :size="36" /></div>
                        <div class="course-card-body">
                            <span class="tag">{{ $curso['categoria'] }}</span>
                            <h3>{{ $curso['titulo'] }}</h3>
                            <p>{{ $curso['nivel'] }} · {{ $curso['duracion'] }}</p>
                            <p><x-icon name="play" :size="15" /> {{ $curso['modalidad'] }}</p>
                            <div class="course-bottom"><strong>{{ $curso['precio'] }}</strong><span>Detalles próximamente</span></div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
@endsection
