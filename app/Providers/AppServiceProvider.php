<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $identity = is_string($email) ? mb_strtolower(trim($email)) : '';
            $response = fn (Request $request, array $headers) => response(
                'Demasiados intentos. Espera un minuto antes de volver a intentarlo.', 429, $headers
            );

            return [
                Limit::perMinute(5)->by('login:'.hash('sha256', $identity.'|'.$request->ip()))->response($response),
                Limit::perMinute(30)->by('login-ip:'.$request->ip())->response($response),
            ];
        });
    }
}
