@props(['curso', 'grupo', 'opcion'])
<div class="inscripcion-gratuita">
    <p id="inscripcion-grupo-{{ $grupo->id }}" class="inscripcion-orientacion">{{ $opcion['mensaje'] }}</p>
    @if ($opcion['permitida'])
        @guest('web')
            <div class="button-row">
                <a class="btn btn-primary" href="{{ route('cursos.acceso', ['curso' => $curso->id]) }}">Iniciar sesión para inscribirme</a>
                <a class="btn btn-outline" href="{{ route('cursos.acceso', ['curso' => $curso->id, 'destino' => 'register']) }}">Crear cuenta</a>
            </div>
        @else
            <form method="POST" action="{{ route('inscripciones.gratuitas.store', ['curso' => $curso->id, 'grupo' => $grupo->id]) }}" aria-describedby="inscripcion-grupo-{{ $grupo->id }}">
                @csrf
                <label class="inscripcion-confirmacion" for="confirmacion-{{ $grupo->id }}">
                    <input id="confirmacion-{{ $grupo->id }}" type="checkbox" name="confirmacion" value="1" required @checked((int) old('grupo_confirmado') === $grupo->id && old('confirmacion'))>
                    <span>Confirmo que deseo inscribirme gratuitamente al grupo {{ $grupo->codigo }} y he revisado los requisitos y condiciones de esta edición.</span>
                </label>
                <button class="btn btn-primary" type="submit">Inscribirme gratis <x-icon name="arrow-right" :size="18" /></button>
            </form>
        @endguest
    @else
        <button class="btn btn-outline" type="button" disabled aria-describedby="inscripcion-grupo-{{ $grupo->id }}">Inscribirme · No disponible</button>
    @endif
</div>
