<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CuentaActivaMiddleware extends AccesoMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->usuarioActivo($request);

        return $next($request);
    }
}
