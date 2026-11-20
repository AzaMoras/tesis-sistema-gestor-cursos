@props(['curso'])
@php($resumen = app(\App\Services\CatalogoCursosService::class)->resumen($curso))
<article class="curso-publico">
    <div class="curso-etiquetas"><span class="tag">{{ $curso->categoria->nombre }}</span><span class="curso-publicado">Publicado</span></div>
    <h3>{{ $curso->titulo }}</h3>
    <p class="curso-institucion">{{ $curso->institucion->nombre }}</p>
    <p class="curso-resumen">{{ \Illuminate\Support\Str::limit($curso->descripcion, 180) }}</p>
    <p class="curso-modalidad"><x-icon name="book" :size="18" /> 100 % virtual · {{ $resumen['modalidades']->implode(' / ') ?: 'Modalidad por definir en una próxima edición' }}</p>
    <div class="curso-oferta">
        <p>{{ $resumen['hayOferta'] ? 'Edición abierta · plazas por confirmar' : 'Sin oferta de inscripción vigente' }}</p>
        @foreach ($resumen['precios'] as $edicion)
            <strong><x-precio-publico :edicion="$edicion" /></strong>
        @endforeach
    </div>
    <a class="btn btn-primary" href="{{ route('cursos.show', $curso->id) }}" aria-label="Ver detalles de {{ $curso->titulo }}">Ver detalles <x-icon name="arrow-right" :size="18" /></a>
</article>
