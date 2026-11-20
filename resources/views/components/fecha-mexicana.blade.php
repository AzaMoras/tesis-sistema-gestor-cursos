@props(['nombre', 'valor' => '', 'original' => null, 'conHora' => false, 'reloj', 'obligatorio' => true])
@php
    $iso = is_string($valor) ? $valor : '';
    $presentacion = $iso;
    if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?\z/', $iso, $partes)) {
        $presentacion = $partes[3].'/'.$partes[2].'/'.$partes[1].($conHora && isset($partes[4]) ? ' '.$partes[4].':'.$partes[5] : '');
    }
    $visible = old($nombre.'_mexicana', $presentacion);
    $visible = is_string($visible) ? $visible : '';
@endphp
<div class="fecha-mexicana" data-fecha-mexicana data-campo="{{ $nombre }}" data-con-hora="{{ $conHora ? 'true' : 'false' }}" data-original="{{ $original ?? '' }}" data-hoy="{{ $reloj->format('Y-m-d') }}" data-min-cierre="{{ $reloj->copy()->startOfMinute()->addMinute()->format('Y-m-d\TH:i') }}">
    <input type="hidden" name="{{ $nombre }}" value="{{ $iso }}" data-fecha-iso>
    <div class="fecha-entrada">
        <input type="text" id="{{ $nombre }}" name="{{ $nombre }}_mexicana" value="{{ $visible }}" data-fecha-texto placeholder="{{ $conHora ? 'dd/mm/aaaa HH:mm' : 'dd/mm/aaaa' }}" maxlength="{{ $conHora ? 16 : 10 }}" autocomplete="off" @required($obligatorio) aria-describedby="{{ $nombre }}-formato {{ $nombre }}-cliente-error{{ $errors->has($nombre) ? ' '.$nombre.'-error' : '' }}" @error($nombre) aria-invalid="true" @enderror>
        <button class="btn btn-outline" type="button" data-fecha-abrir aria-label="Abrir calendario de {{ $nombre === 'fecha_inicio' ? 'fecha inicial' : ($nombre === 'fecha_fin' ? 'fecha final' : 'cierre de inscripciones') }}" aria-expanded="false" aria-controls="{{ $nombre }}-calendario" hidden><x-icon name="calendar" :size="18" />Calendario</button>
    </div>
    <p id="{{ $nombre }}-formato" class="gestion-ayuda">{{ $conHora ? 'Formato: dd/mm/aaaa HH:mm, con hora de 00:00 a 23:59.' : 'Formato: dd/mm/aaaa. Puedes escribir o elegir en el calendario.' }}@if ($original) Se permite conservar la fecha original; los cambios deben respetar los límites actuales.@endif</p>
    <p id="{{ $nombre }}-cliente-error" class="fecha-error" role="status" aria-live="polite" hidden></p>
    <div class="fecha-calendario" id="{{ $nombre }}-calendario" role="dialog" aria-label="Seleccionar fecha en formato mexicano" hidden>
        <div class="fecha-calendario-nav"><button type="button" class="btn btn-outline btn-sm" data-mes-anterior aria-label="Mes anterior">Anterior</button><button type="button" class="btn btn-outline btn-sm" data-mes-siguiente aria-label="Mes siguiente">Siguiente</button><button type="button" class="btn btn-outline btn-sm" data-fecha-cerrar aria-label="Cerrar calendario">Cerrar</button></div>
        <div class="fecha-mes-ano"><div><label for="{{ $nombre }}-mes">Mes</label><select id="{{ $nombre }}-mes" data-calendario-mes>
            @foreach (['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $indice => $mes)<option value="{{ $indice + 1 }}">{{ $mes }}</option>@endforeach
        </select></div><div><label for="{{ $nombre }}-ano">Año</label><input id="{{ $nombre }}-ano" type="number" min="1" max="9999" data-calendario-ano aria-label="Año del calendario"></div></div>
        <p class="fecha-mes-anuncio" data-calendario-anuncio aria-live="polite"></p>
        <div class="fecha-semana" aria-hidden="true"><span>Lu</span><span>Ma</span><span>Mi</span><span>Ju</span><span>Vi</span><span>Sá</span><span>Do</span></div>
        <div class="fecha-dias" data-calendario-dias role="group" aria-label="Días del mes"></div>
        @if ($conHora)
            <div class="fecha-hora"><div><label for="{{ $nombre }}-hora">Hora (0 a 23)</label><input id="{{ $nombre }}-hora" type="number" min="0" max="23" step="1" data-calendario-hora></div><div><label for="{{ $nombre }}-minuto">Minutos (0 a 59)</label><input id="{{ $nombre }}-minuto" type="number" min="0" max="59" step="1" data-calendario-minuto></div></div>
            <p class="gestion-ayuda">Hora en {{ config('app.timezone') }}. Cambiarla actualiza el campo de fecha y hora.</p>
        @endif
        <p class="gestion-ayuda">Usa las flechas para recorrer los días, Inicio/Fin para la semana y Esc para cerrar.</p>
    </div>
</div>
