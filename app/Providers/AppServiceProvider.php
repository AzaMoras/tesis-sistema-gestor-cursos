<?php

namespace App\Providers;

use App\Services\Soporte\ReglasOperacion;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReglasOperacion::class, fn () => new ReglasOperacion(
            estados: config('operacion.estados'),
            apertura: config('operacion.apertura'),
            cupoNuloIlimitado: config('operacion.cupo_nulo_ilimitado'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('inscripcion-gratuita', fn (Request $request) => [
            Limit::perMinute(10)->by('inscripcion-usuario:'.$request->user()->getAuthIdentifier())
                ->response(fn (Request $request, array $headers) => response('Demasiados intentos de inscripción. Espera un minuto y revisa Mis cursos antes de repetir.', 429, $headers)),
            Limit::perMinute(30)->by('inscripcion-ip:'.$request->ip()),
        ]);
        VerifyEmail::toMailUsing(fn (object $user, string $url) => (new MailMessage)
            ->subject('Verifica tu correo en Aula Verde')
            ->greeting('Hola, '.$user->name)
            ->line('Verifica tu correo electrónico para acceder a las funciones de participante.')
            ->action('Verificar correo electrónico', $url)
            ->line('El enlace vence en '.config('auth.verification.expire', 60).' minutos.')
            ->line('Si no creaste esta cuenta, puedes ignorar este mensaje.')
            ->salutation('Aula Verde'));

        RateLimiter::for('registro', fn (Request $request) => Limit::perMinute(5)
            ->by('registro:'.$request->ip())
            ->response(fn (Request $request, array $headers) => response('Demasiados intentos de registro. Espera un minuto.', 429, $headers)));

        RateLimiter::for('verificacion', fn (Request $request) => Limit::perMinute(3)
            ->by('verificacion:'.$request->user()->getAuthIdentifier())
            ->response(fn (Request $request, array $headers) => response('Demasiados intentos de verificación. Espera un minuto.', 429, $headers)));

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
