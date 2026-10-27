<?php

namespace App\Http\Middleware;

use App\Models\Institucion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class AccesoMiddleware
{
    protected function usuarioActivo(Request $request): User
    {
        $usuario = $request->user();

        if (! $usuario instanceof User || ! $usuario->exists || $usuario->trashed()) {
            throw new HttpException(401, 'Debes iniciar sesión para acceder.');
        }

        // users.estado_cuenta es varchar(25); "pendiente" es su valor inicial.
        if ($usuario->estado_cuenta !== 'activa') {
            throw new HttpException(403, 'Tu cuenta no está activa.');
        }

        return $usuario;
    }

    /** @return array{0: User, 1: Institucion} */
    protected function contexto(Request $request): array
    {
        $usuario = $this->usuarioActivo($request);

        // El parámetro selecciona el ámbito; nunca concede pertenencia ni roles.
        // No se aceptan instituciones, roles o permisos del cuerpo del formulario.
        $seleccion = $request->route('institucion');

        if ($seleccion instanceof Institucion) {
            $seleccion = $seleccion->getKey();
        }

        $institucionId = null;

        if ($seleccion !== null) {
            if (! is_int($seleccion) && ! is_string($seleccion)) {
                throw new HttpException(403, 'La institución seleccionada no es válida.');
            }

            $institucionId = filter_var($seleccion, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($institucionId === false) {
                throw new HttpException(403, 'La institución seleccionada no es válida.');
            }
        }

        // whereHas evita duplicar instituciones cuando hay varios roles asignados.
        $consulta = Institucion::query()
            ->where('instituciones.estado', 'activa')
            ->whereHas('usuarios', function (Builder $query) use ($usuario): void {
                $query->whereKey($usuario->getKey());
            });

        if ($institucionId !== null) {
            $consulta->whereKey($institucionId);
        }

        // Sin parámetro solo se admite un ámbito inequívoco; no se toma el primero.
        $instituciones = $consulta->limit(2)->get();

        if ($instituciones->count() !== 1) {
            throw new HttpException(403, 'Selecciona una institución activa a la que tengas acceso.');
        }

        $institucion = $instituciones->first();
        $request->attributes->set('institucion_autorizada', $institucion);

        return [$usuario, $institucion];
    }
}
