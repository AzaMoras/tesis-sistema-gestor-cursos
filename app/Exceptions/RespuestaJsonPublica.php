<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class RespuestaJsonPublica
{
    private const DELEGACION = self::class.'.delegacion';

    private const MENSAJES_PUBLICOS_403 = [
        ...MensajesPublicos403::AULA,
        'Tu cuenta no está activa.',
        'La cuenta no está activa.',
        'Selecciona una institución activa a la que tengas acceso.',
        'Your email address is not verified.',
        'Invalid signature.',
    ];

    public function registrarDelegacion(Throwable $excepcion, Request $solicitud): void
    {
        // Laravel llama a los callbacks de renderizado solo si el render() propio
        // de la excepción no produjo una respuesta.
        if (method_exists($excepcion, 'render')) {
            $solicitud->attributes->set(self::DELEGACION, $excepcion);
        }
    }

    public function __invoke(Response $respuesta, Throwable $excepcion, Request $solicitud): Response
    {
        $delegacion = $solicitud->attributes->get(self::DELEGACION);
        $solicitud->attributes->remove(self::DELEGACION);

        if (! $respuesta instanceof JsonResponse
            || $excepcion instanceof ValidationException
            || $excepcion instanceof HttpResponseException
            || $excepcion instanceof Responsable
            || (method_exists($excepcion, 'render') && $delegacion !== $excepcion)) {
            return $respuesta;
        }

        $estado = $respuesta->getStatusCode();
        $mensaje = match (true) {
            $estado >= 500 && $estado < 600 => 'Ocurrió un error inesperado.',
            $excepcion instanceof AuthenticationException && $estado === 401 => 'Unauthenticated.',
            $estado >= 400 && $estado < 500 && $excepcion instanceof HttpExceptionInterface
                => $this->mensajeHttp($estado, $excepcion->getMessage()),
            default => null,
        };

        if ($mensaje === null) {
            return $respuesta;
        }

        $respuesta->setData(['message' => $mensaje]);
        foreach (['Content-Length', 'ETag', 'Content-MD5', 'Digest', 'Content-Digest', 'Repr-Digest'] as $encabezado) {
            $respuesta->headers->remove($encabezado);
        }

        return $respuesta;
    }

    private function mensajeHttp(int $estado, string $mensaje): string
    {
        return match ($estado) {
            401 => 'Unauthenticated.',
            403 => in_array($mensaje, self::MENSAJES_PUBLICOS_403, true)
                ? $mensaje
                : MensajesPublicos403::GENERICO,
            404 => 'Página no encontrada.',
            405 => 'Método no permitido.',
            419 => 'CSRF token mismatch.',
            422 => 'No se pudo procesar la solicitud.',
            429 => 'Too Many Attempts.',
            default => 'No se pudo procesar la solicitud.',
        };
    }
}
