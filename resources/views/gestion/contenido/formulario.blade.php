@extends('gestion.contenido.base')
@section('contenido-gestion')
    @php
        $parametros = [$institucion->id, $curso->id];
        if ($esMaterial) { $parametros[] = $modulo->id; }
        if ($registro) { $parametros[] = $registro->id; }
        $entidad = $esMaterial ? 'material' : 'modulo';
        $rutaVolver = $esMaterial ? route('gestion.contenido.modulo.show', [$institucion->id, $curso->id, $modulo->id]) : route('gestion.contenido.curso', [$institucion->id, $curso->id]);
        $valor = function ($campo, $predeterminado = '') use ($registro) {
            $dato = old($campo, $registro?->$campo ?? $predeterminado);
            return is_scalar($dato) ? $dato : '';
        };
    @endphp
    <h3>{{ $lectura ? 'Revisar material' : ($registro ? 'Editar ' : 'Crear ').($esMaterial ? 'material' : 'módulo') }}</h3>
    @if ($esMaterial)<p>Módulo: {{ $modulo->titulo }}</p>@endif
    @if ($lectura)
        <dl class="gestion-detalles">
            <dt>Título</dt><dd>{{ $registro->titulo }}</dd>
            <dt>Tipo</dt><dd>{{ \App\Services\ContenidoCursoService::TIPOS[$registro->tipo] ?? 'Tipo histórico, solo consulta' }}</dd>
            <dt>Orden</dt><dd>{{ $registro->orden }}</dd>
            <dt>Estado</dt><dd>{{ $registro->publicado ? 'Publicado' : 'Borrador, no visible para participantes' }}</dd>
            <dt>Recurso externo</dt><dd>
                @if (\App\Services\Soporte\UrlMaterial::segura($registro->url_externa) && $registro->ruta_archivo === null)
                    <a class="contenido-url" href="{{ $registro->url_externa }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{ $registro->url_externa }}<span class="sr-only"> (abre una pestaña nueva)</span></a>
                @else
                    Este recurso no está disponible como enlace HTTPS externo autorizado en esta fase.
                @endif
            </dd>
        </dl>
        <p class="gestion-ayuda">Comprueba el contenido, los derechos de uso y el destino del enlace antes de publicarlo. El recurso se abre en una pestaña nueva; su disponibilidad depende del proveedor externo.</p>
        @unless ($protegido)
            <div class="gestion-acciones">
                @if (! $registro->publicado && $registro->ruta_archivo === null)
                    <a class="btn btn-outline" href="{{ route('gestion.contenido.material.edit', $parametros) }}"><x-icon name="edit" :size="18" />Editar borrador</a>
                    <form method="POST" action="{{ route('gestion.contenido.material.publish', $parametros) }}">@csrf<button type="submit" class="btn btn-primary"><x-icon name="check" :size="18" />Publicar material</button></form>
                @elseif ($registro->publicado)
                    <form method="POST" action="{{ route('gestion.contenido.material.hide', $parametros) }}">@csrf<button type="submit" class="btn btn-outline">Ocultar material</button></form>
                @endif
            </div>
        @endunless
    @else
        @if ($esMaterial && $bloqueado && ! $protegido)<p class="gestion-aviso">Este material está publicado o corresponde a un archivo histórico. Revisa su estado; solo los borradores externos pueden editarse.</p>@endif
        <form class="auth-form gestion-formulario" method="POST" action="{{ route('gestion.contenido.'.$entidad.'.'.($registro ? 'update' : 'store'), $parametros) }}">
            @csrf
            @if ($registro) @method('PUT') @endif
            <fieldset @disabled($bloqueado)>
                <legend>Datos del {{ $esMaterial ? 'material' : 'módulo' }}</legend>
                <div><label for="titulo">Título</label><input id="titulo" name="titulo" value="{{ $valor('titulo') }}" required maxlength="180" @error('titulo') aria-invalid="true" aria-describedby="titulo-error" @enderror>@error('titulo')<p class="contenido-error" id="titulo-error">{{ $message }}</p>@enderror</div>
                @if ($esMaterial)
                    <div><label for="tipo">Tipo de material</label><select id="tipo" name="tipo" required><option value="">Selecciona un tipo</option>@foreach (\App\Services\ContenidoCursoService::TIPOS as $clave => $etiqueta)<option value="{{ $clave }}" @selected($valor('tipo') === $clave)>{{ $etiqueta }}</option>@endforeach</select>@error('tipo')<p class="contenido-error">{{ $message }}</p>@enderror</div>
                    <div><label for="url_externa">Enlace HTTPS</label><input id="url_externa" name="url_externa" type="url" value="{{ $valor('url_externa') }}" maxlength="1000" required aria-describedby="url-ayuda @error('url_externa') url-error @enderror" @error('url_externa') aria-invalid="true" @enderror><p class="gestion-ayuda" id="url-ayuda">Enlace público o autorizado por la institución. Sin contraseñas en la URL, archivos locales ni código HTML. Se abrirá en una pestaña nueva.</p>@error('url_externa')<p class="contenido-error" id="url-error">{{ $message }}</p>@enderror</div>
                @else
                    <div><label for="descripcion">Descripción</label><textarea id="descripcion" name="descripcion" rows="4" maxlength="10000">{{ $valor('descripcion') }}</textarea>@error('descripcion')<p class="contenido-error">{{ $message }}</p>@enderror</div>
                    <div><label for="obligatorio">Carácter del módulo</label><select id="obligatorio" name="obligatorio" required><option value="1" @selected((string) $valor('obligatorio', '1') === '1')>Obligatorio</option><option value="0" @selected((string) $valor('obligatorio', '1') === '0')>Complementario</option></select>@error('obligatorio')<p class="contenido-error">{{ $message }}</p>@enderror</div>
                @endif
                <div><label for="orden">Orden</label><input id="orden" name="orden" type="number" min="1" max="65535" step="1" required value="{{ $valor('orden', $siguienteOrden) }}" aria-describedby="orden-ayuda"><p class="gestion-ayuda" id="orden-ayuda">Número único dentro del {{ $esMaterial ? 'módulo' : 'curso' }}. Puedes dejar espacios (10, 20, 30). Para cambiar el orden, elige una posición libre; no se intercambian posiciones automáticamente.</p>@error('orden')<p class="contenido-error">{{ $message }}</p>@enderror</div>
                @if ($esMaterial)<p class="gestion-ayuda">Guardar conserva el material en borrador. Después podrás revisarlo y publicarlo mediante una acción diferenciada.</p>@endif
                @unless ($bloqueado)<button type="submit" class="btn btn-primary">{{ $esMaterial ? 'Guardar borrador' : 'Guardar módulo' }}</button>@endunless
            </fieldset>
        </form>
    @endif
    <p class="gestion-volver"><a class="btn btn-outline" href="{{ $rutaVolver }}">{{ $esMaterial ? 'Volver a los materiales' : 'Volver a los módulos' }}</a></p>
@endsection
