@extends('gestion.base')
@section('encabezado', ($registro ? ($editable ? 'Editar ' : 'Ver detalles: ') : 'Crear ').['categorias' => 'categoría', 'cursos' => 'curso', 'ediciones' => 'edición', 'grupos' => 'grupo'][$tipo])
@section('gestion')
    @if ($registro)
        <div class="gestion-toolbar"><p>Estado: <strong>{{ $registro->estado }}</strong> · Identificador: {{ $registro->id }}</p>
            @if ($soloLectura && $puedeEditar)<a class="btn btn-primary" href="{{ route('gestion.'.$tipo.'.edit', [$institucion->id, $registro->id]) }}"><x-icon name="edit" :size="18" />Editar</a>@endif
        </div>
    @endif
    @if (! $puedeEditar)<p class="gestion-aviso">Este registro está protegido por su estado o sus relaciones operativas. Puedes consultar sus datos.</p>@endif
    @if ($cursoPublicado)
        <p class="gestion-aviso" id="curso-ayuda">La publicación se conserva. Esta edición permite correcciones de información general; la categoría, requisitos y condiciones permanecen protegidos.@if ($camposCurso === ['titulo']) Hay inscripciones existentes: solo puedes corregir el título. La descripción y los objetivos se conservan.@endif</p>
    @elseif ($tipo === 'ediciones')
        <p class="gestion-aviso">Ambas modalidades son virtuales. Introduce fechas y horas en {{ config('app.timezone') }}. El cierre es opcional y debe ser igual o anterior a las 00:00 de la fecha final.</p>
    @elseif ($tipo === 'grupos')
        <p class="gestion-aviso">Define un cupo entero positivo. Para abrir el grupo, primero publica el curso y abre su edición.</p>
    @elseif ($tipo === 'categorias')
        <p class="gestion-aviso">El identificador de URL se sugiere a partir del nombre. Puedes ajustarlo antes de guardar; debe ser único en esta institución.</p>
    @else
        <p class="gestion-aviso">Guarda el curso como borrador y revisa sus datos antes de publicarlo. Los requisitos y las condiciones de las ediciones se gestionan por separado.</p>
    @endif
    @php($campos = match ($tipo) {
        'categorias' => ['nombre' => ['Nombre', 'text', 120], 'slug' => ['Identificador de URL (minúsculas y guiones)', 'text', 150], 'descripcion' => ['Descripción', 'textarea', 10000]],
        'cursos' => ['titulo' => ['Título', 'text', 180], 'descripcion' => ['Descripción', 'textarea', 20000], 'objetivos' => ['Objetivos', 'textarea', 10000]],
        'ediciones' => ['clave' => ['Clave', 'text', 60], 'fecha_inicio' => ['Fecha inicial', 'date', null], 'fecha_fin' => ['Fecha final', 'date', null], 'cierre_inscripciones_at' => ['Cierre de inscripciones (opcional)', 'datetime-local', null], 'precio' => ['Precio (0 para gratuito)', 'text', 13]],
        'grupos' => ['codigo' => ['Código', 'text', 60], 'cupo_maximo' => ['Cupo positivo', 'number', null]],
    })
    @if ($soloLectura)
        <dl class="gestion-detalles">
            @if ($tipo !== 'categorias')
                <dt>{{ ['cursos' => 'Categoría', 'ediciones' => 'Curso', 'grupos' => 'Edición'][$tipo] }}</dt>
                <dd>{{ match ($tipo) { 'cursos' => $registro->categoria->nombre, 'ediciones' => $registro->curso->titulo, 'grupos' => $registro->edicion->clave.' · '.$registro->edicion->curso->titulo } }}</dd>
            @endif
            @foreach ($campos as $campo => [$etiqueta, $control, $maximo])
                <dt>{{ $etiqueta }}</dt><dd>{{ ($datos[$campo] ?? null) !== null && $datos[$campo] !== '' ? $datos[$campo] : 'Sin especificar' }}</dd>
            @endforeach
            @if ($tipo === 'ediciones')<dt>Modalidad virtual</dt><dd>{{ $registro->modalidad === 'autogestionado' ? 'Autogestionado' : 'Acompañado por instructor' }}</dd><dt>Moneda</dt><dd>{{ $registro->moneda }}</dd>@endif
        </dl>
    @else
    <form class="auth-form gestion-formulario" method="POST" action="{{ $registro ? route('gestion.'.$tipo.'.update', [$institucion->id, $registro->id]) : route('gestion.'.$tipo.'.store', $institucion->id) }}">
        @csrf
        @if ($registro) @method('PUT') @endif
        <fieldset @disabled(! $editable)>
            <legend>Datos de {{ ['categorias' => 'categoría', 'cursos' => 'curso', 'ediciones' => 'edición', 'grupos' => 'grupo'][$tipo] }}</legend>
            @if ($tipo !== 'categorias')
                @php($campoPadre = ['cursos' => 'categoria_id', 'ediciones' => 'curso_id', 'grupos' => 'edicion_id'][$tipo])
                <div><label for="{{ $campoPadre }}">{{ ['cursos' => 'Categoría', 'ediciones' => 'Curso', 'grupos' => 'Edición'][$tipo] }}</label>
                    <select id="{{ $campoPadre }}" name="{{ $campoPadre }}" required @disabled($cursoPublicado) @if ($registro && $tipo !== 'cursos' || $cursoPublicado) aria-describedby="padre-ayuda" @endif>
                        <option value="">Selecciona una opción</option>
                        @foreach ($padres as $padre)
                            @if (! $registro || $tipo === 'cursos' || $padre->id == $registro->$campoPadre)
                                <option value="{{ $padre->id }}" @selected(($cursoPublicado ? ($datos[$campoPadre] ?? '') : old($campoPadre, $datos[$campoPadre] ?? '')) == $padre->id)>{{ $padre->nombre ?? $padre->titulo ?? ($padre->clave.' · '.$padre->curso->titulo) }}</option>
                            @endif
                        @endforeach
                    </select>
                    @if ($registro && $tipo !== 'cursos' || $cursoPublicado)<p id="padre-ayuda" class="gestion-ayuda">La relación de origen se conserva.</p>@endif
                    @error($campoPadre)<p role="alert">{{ $message }}</p>@enderror
                </div>
            @endif
            @foreach ($campos as $campo => [$etiqueta, $control, $maximo])
                @php($bloqueado = $cursoPublicado && ! in_array($campo, $camposCurso, true))
                @php($valorCampo = $bloqueado ? ($datos[$campo] ?? '') : old($campo, $datos[$campo] ?? ($campo === 'precio' ? '0.00' : '')))
                @php($ayudasCampo = implode(' ', array_filter([$bloqueado ? 'curso-ayuda' : null, $campo === 'slug' ? 'slug-ayuda' : null, $campo === 'precio' ? 'precio-ayuda' : null, $campo === 'titulo' && $cursoPublicado ? 'titulo-ayuda' : null, $errors->has($campo) ? $campo.'-error' : null])))
                <div>
                    <label for="{{ $campo }}">{{ $etiqueta }}</label>
                    @if ($control === 'textarea')
                        <textarea id="{{ $campo }}" name="{{ $campo }}" rows="4" maxlength="{{ $maximo }}" @readonly($bloqueado) @if ($ayudasCampo) aria-describedby="{{ $ayudasCampo }}" @endif @required($tipo === 'cursos' && $campo === 'descripcion') @error($campo) aria-invalid="true" @enderror>{{ $valorCampo }}</textarea>
                    @else
                        <input id="{{ $campo }}" name="{{ $campo }}" type="{{ $control }}" value="{{ $valorCampo }}" @if ($maximo) maxlength="{{ $maximo }}" @endif @if ($control === 'number') min="1" max="4294967295" step="1" @endif @if ($campo === 'slug' && ! $registro) data-slug-sugerido data-slug-manual="{{ old('slug') !== null ? 'true' : 'false' }}" @endif @if ($ayudasCampo) aria-describedby="{{ $ayudasCampo }}" @endif @if ($campo === 'precio') inputmode="decimal" @endif @required($campo !== 'cierre_inscripciones_at') @error($campo) aria-invalid="true" @enderror>
                    @endif
                    @if ($campo === 'slug')<p id="slug-ayuda" class="gestion-ayuda">Ejemplo: tecnologia-basica. Una vez que lo edites, la sugerencia automática se detendrá.</p>@endif
                    @if ($campo === 'precio')<p id="precio-ayuda" class="gestion-ayuda">Importe en MXN, con punto decimal y hasta dos decimales. Usa 0.00 para una edición gratuita.</p>@endif
                    @if ($campo === 'titulo' && $cursoPublicado)<p id="titulo-ayuda" class="gestion-ayuda">Corrige su redacción conservando la identidad y el alcance del curso.</p>@endif
                    @error($campo)<p id="{{ $campo }}-error">{{ $message }}</p>@enderror
                </div>
            @endforeach
            @if ($tipo === 'ediciones')
                <div><label for="modalidad">Modalidad virtual</label><select id="modalidad" name="modalidad" required>
                    <option value="autogestionado" @selected(old('modalidad', $datos['modalidad'] ?? '') === 'autogestionado')>Autogestionado virtual</option>
                    <option value="acompanada" @selected(old('modalidad', $datos['modalidad'] ?? '') === 'acompanada')>Acompañado por instructor, virtual</option>
                </select></div>
                <div><label for="moneda">Moneda</label><select id="moneda" name="moneda"><option value="MXN">MXN</option></select></div>
            @endif
            @if ($editable)<button type="submit" class="btn btn-primary">Guardar {{ $registro ? 'cambios' : ($tipo === 'categorias' ? 'categoría' : 'borrador') }}</button>@endif
        </fieldset>
    </form>
    @endif
    <p class="gestion-volver"><a class="btn btn-outline" href="{{ route('gestion.'.$tipo.'.index', $institucion->id) }}">Volver al listado</a></p>
@endsection
