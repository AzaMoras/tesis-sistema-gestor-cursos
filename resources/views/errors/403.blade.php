@extends('errors.layout')

@section('codigo', '403')
@section('titulo', 'Acceso restringido')

@section('mensaje')
    @php
        $mensaje = $exception->getMessage();
        if (! in_array($mensaje, \App\Exceptions\MensajesPublicos403::AULA, true)) {
            $mensaje = \App\Exceptions\MensajesPublicos403::GENERICO;
        }
    @endphp
    {{ $mensaje }}
@endsection
