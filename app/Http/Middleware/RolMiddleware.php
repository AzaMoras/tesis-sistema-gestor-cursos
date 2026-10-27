<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RolMiddleware extends AccesoMiddleware
{
    protected const ROLES = [];

    /** Los parámetros del alias rol son alternativas (basta una coincidencia). */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        [$usuario, $institucion] = $this->contexto($request);

        // En los cinco middleware concretos el rol es fijo y no se puede sustituir.
        $requeridos = static::ROLES !== [] ? static::ROLES : array_values(array_unique($roles));

        if ($requeridos === [] || ! $usuario->rolesEnInstitucion($institucion->getKey())
            ->whereIn('roles.clave', $requeridos)
            ->exists()) {
            throw new HttpException(403, 'No tienes el rol requerido en esta institución.');
        }

        return $next($request);
    }
}
