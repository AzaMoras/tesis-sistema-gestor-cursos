@extends('layouts.sitio-publico')
@section('titulo', $curso->titulo)

@section('contenido')
    <div class="container curso-detalle">
        <a class="btn btn-outline" href="{{ route('cursos.index') }}">Explorar cursos <x-icon name="arrow-right" :size="18" /></a>
        <header class="curso-cabecera">
            <p class="eyebrow">CURSO PUBLICADO · 100 % VIRTUAL</p>
            <h1>{{ $curso->titulo }}</h1>
            <p>{{ $curso->institucion->nombre }} · {{ $curso->categoria->nombre }}</p>
        </header>
        @if ($errors->any())
            <div class="catalogo-aviso" role="alert"><h2>No se completó la inscripción</h2><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p>Revisa las condiciones del grupo antes de volver a confirmar.</p></div>
        @endif
        <section class="curso-seccion" aria-labelledby="descripcion-titulo"><h2 id="descripcion-titulo">Acerca del curso</h2><p class="texto-con-saltos">{{ $curso->descripcion }}</p></section>
        <section class="curso-seccion" aria-labelledby="objetivos-titulo"><h2 id="objetivos-titulo">Lo que aprenderás</h2><p class="texto-con-saltos">{{ $curso->objetivos ?: 'La institución todavía no ha publicado los objetivos.' }}</p></section>
        <section class="curso-seccion" aria-labelledby="requisitos-titulo">
            <h2 id="requisitos-titulo">Requisitos y condiciones para participar</h2>
            @forelse ($curso->requisitos as $requisito)
                <div class="requisito-publico"><h3>{{ $requisito->nombre }} <span class="tag">{{ $requisito->pivot->obligatorio ? 'Obligatorio' : 'Opcional' }}</span></h3>
                    @if ($requisito->descripcion)<p class="texto-con-saltos">{{ $requisito->descripcion }}</p>@endif
                    @if ($requisito->requiere_documento)<p>Este requisito requiere evidencia documental y revisión institucional.</p>@endif
                </div>
            @empty
                <p>No hay requisitos publicados para este curso.</p>
            @endforelse
            @if ($curso->requisitos_count !== $curso->requisitos->count())
                <p class="catalogo-aviso">Hay condiciones que requieren confirmación institucional antes de solicitar la inscripción.</p>
            @endif
            <p>Consulta las fechas, el precio y los criterios registrados de cada edición. La duración de la edición no determina por sí sola la vigencia de acceso académico.</p>
        </section>
        <section class="curso-seccion" aria-labelledby="ediciones-titulo">
            <h2 id="ediciones-titulo">Ediciones publicadas</h2>
            <p class="text-muted">Fechas en dd/mm/aaaa y horas de 24 horas en {{ config('app.timezone') }}@if (config('app.timezone') === 'UTC') (tiempo universal coordinado; la hora local de México es diferente)@endif.</p>
            <p class="text-muted">Solo se muestran ediciones abiertas y grupos abiertos. La apertura no garantiza plazas ni confirma una inscripción.</p>
            @forelse ($curso->ediciones as $edicion)
                <article class="edicion-publica">
                    <div class="results-heading"><h3>Edición {{ $edicion->clave }}</h3><span class="tag">{{ $catalogo::MODALIDADES[$edicion->modalidad] }} · Virtual</span></div>
                    <dl class="edicion-datos">
                        <div><dt>Fechas de la edición</dt><dd>{{ $edicion->fecha_inicio?->format('d/m/Y') ?: 'Por confirmar' }} a {{ $edicion->fecha_fin?->format('d/m/Y') ?: 'Por confirmar' }}</dd></div>
                        <div><dt>Precio registrado</dt><dd><x-precio-publico :edicion="$edicion" /></dd></div>
                        <div><dt>Cierre de inscripciones</dt><dd>{{ $edicion->cierre_inscripciones_at?->format('d/m/Y H:i') ?: 'Sin fecha de cierre registrada' }}@if ($edicion->cierre_inscripciones_at) ({{ config('app.timezone') }})@endif</dd></div>
                        <div><dt>Periodo de inscripción</dt><dd>{{ $catalogo->enPeriodoInscripcion($edicion) ? 'Vigente; disponibilidad por confirmar' : 'Fuera de plazo o fechas por confirmar' }}</dd></div>
                        <div><dt>Calificación mínima registrada</dt><dd>{{ $edicion->calificacion_minima }} / 100</dd></div>
                        @if ($edicion->modalidad === 'acompanada')<div><dt>Asistencia mínima registrada</dt><dd>{{ $edicion->asistencia_minima }} %</dd></div>@endif
                    </dl>
                    @forelse ($edicion->grupos as $grupo)
                        <div class="grupo-publico" id="grupo-{{ $grupo->id }}"><h4>Grupo {{ $grupo->codigo }}</h4>
                            <p>{{ $grupo->cupo_maximo > 0 ? 'Capacidad máxima registrada: '.$grupo->cupo_maximo.'. No equivale a plazas disponibles.' : 'Capacidad pendiente de confirmar.' }}</p>
                            @if ($edicion->modalidad === 'acompanada' && $grupo->sesiones->isNotEmpty())
                                <p>Horarios de sesiones virtuales ({{ config('app.timezone') }}):</p>
                                <ul>@foreach ($grupo->sesiones as $sesion)<li>{{ $sesion->inicio_at->format('d/m/Y H:i') }} a {{ $sesion->fin_at->format('d/m/Y H:i') }}</li>@endforeach</ul>
                            @else
                                <p>No hay horarios de sesiones publicados para este grupo.</p>
                            @endif
                            <x-inscripcion-gratuita :curso="$curso" :grupo="$grupo" :opcion="$opciones[$grupo->id]" />
                        </div>
                    @empty
                        <p>No hay grupos abiertos publicados para esta edición.</p>
                    @endforelse
                </article>
            @empty
                <p class="catalogo-aviso">Este curso está publicado, pero todavía no tiene ediciones abiertas para consultar.</p>
            @endforelse
        </section>
        <aside class="catalogo-aviso" aria-labelledby="inscripcion-titulo"><h2 id="inscripcion-titulo">¿Te interesa este curso?</h2><p>Elige un grupo gratuito habilitado y confirma expresamente tu inscripción. Consultar esta página no reserva plazas. Las ediciones de pago todavía no admiten inscripción en línea.</p>
            @if (auth('web')->user()?->participante)<div class="button-row"><a class="btn btn-outline" href="{{ route('mis-cursos') }}">Ver Mis cursos</a></div>@endif
        </aside>
    </div>
@endsection
