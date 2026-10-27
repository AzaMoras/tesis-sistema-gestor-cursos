<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PermisoMiddleware extends AccesoMiddleware
{
    /** Las claves se declaran en la ruta; deben cumplirse todas. */
    public function handle(Request $request, Closure $next, string ...$permisos): Response
    {
        [$usuario, $institucion] = $this->contexto($request);
        $requeridos = array_values(array_unique($permisos));

        if ($requeridos === [] || in_array('', $requeridos, true)) {
            throw new HttpException(403, 'La ruta requiere permisos específicos.');
        }

        // permisosEnInstitucion usa roles -> rol_permiso -> permisos y usuario_rol.
        $cantidad = $usuario->permisosEnInstitucion($institucion->getKey())
            ->whereIn('permisos.clave', $requeridos)
            ->count();

        if ($cantidad !== count($requeridos)) {
            throw new HttpException(403, 'No tienes los permisos requeridos en esta institución.');
        }

        // Administrador tampoco tiene una excepción automática a los permisos.
        return $next($request);
    }
}
