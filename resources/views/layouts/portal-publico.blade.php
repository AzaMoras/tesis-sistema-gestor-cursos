@extends('layouts.sitio-publico')

@section('contenido')
    <section class="catalog-hero">
        <div class="container">
            <p class="eyebrow">Formación en línea</p>
            <h1>@yield('encabezado_portal', 'Explora cursos que se adaptan a ti')</h1>
            <p class="text-muted">@yield('descripcion_portal', 'Consulta programas, requisitos, modalidades y fechas sin crear una cuenta.')</p>
        </div>
    </section>
    <div class="container section-space">
        @yield('contenido_portal')
    </div>
@endsection
