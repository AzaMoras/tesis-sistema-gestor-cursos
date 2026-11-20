@props(['edicion'])
@if ((float) $edicion->precio === 0.0)
    Gratuito · {{ $edicion->moneda }}
@else
    {{ number_format((float) $edicion->precio, 2, '.', ',') }} {{ $edicion->moneda }}
@endif
