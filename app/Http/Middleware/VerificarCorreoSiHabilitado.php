<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarCorreoSiHabilitado
{
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): Response
    {
        if (config('auth.verification.enabled')) {
            return app(EnsureEmailIsVerified::class)->handle($request, $next, $redirectToRoute);
        }

        return $next($request);
    }
}
