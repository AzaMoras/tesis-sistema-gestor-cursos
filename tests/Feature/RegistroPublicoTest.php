<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RegistroParticipanteService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Support\ServiceTestCase;

class RegistroPublicoTest extends ServiceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Conserva la cobertura del flujo opcional que podrá reactivarse.
        config(['auth.verification.enabled' => true]);
    }

    private function datos(array $extra = []): array
    {
        return $extra + ['name' => 'Persona de prueba', 'email' => 'nuevo@example.test',
            'password' => 'UnaClaveSegura!123', 'password_confirmation' => 'UnaClaveSegura!123'];
    }

    private function enlace(User $user, array $extra = []): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), $extra + [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
    }

    public function test_formulario_publico_integrado_sin_documentos_ni_privilegios(): void
    {
        $this->get('/register')->assertOk()->assertViewIs('auth.register')
            ->assertSee('name="_token"', false)->assertSee('autocomplete="new-password"', false)
            ->assertSee('for="name"', false)->assertSee('for="email"', false)
            ->assertSee('data-theme-toggle', false)->assertDontSee('name="curp"', false)
            ->assertDontSee('name="ine"', false)->assertDontSee('name="institution_id"', false)
            ->assertDontSee('name="role"', false);
        $this->get('/')->assertSee('href="'.route('register').'"', false);
        $this->get('/login')->assertSee('href="'.route('register').'"', false);
    }

    public function test_alta_normalizada_atomica_activa_sin_roles_y_con_evento_nativo(): void
    {
        Notification::fake();
        $eventos = [];
        Event::listen(Registered::class, function (Registered $event) use (&$eventos): void {
            $eventos[] = $event->user->id;
            self::assertNotNull($event->user->participante);
        });
        $this->withSession(['url.intended' => 'https://externo.test']);
        $id = session()->getId();
        $token = session()->token();
        $this->post('/register', $this->datos(['name' => ' Persona de prueba ', 'email' => ' NUEVO@EXAMPLE.TEST ',
            'role' => 'administrador', 'rol_id' => 1, 'roles' => ['administrador'],
            'institution_id' => $this->institucion->id, 'institucion_id' => $this->institucion->id,
            'permissions' => ['*'], 'account_status' => 'suspendida', 'estado_cuenta' => 'suspendida',
            'email_verified_at' => now(), 'user_id' => $this->admin->id]))
            ->assertRedirect('/email/verify')->assertSessionMissing('url.intended');
        $user = User::where('email', 'nuevo@example.test')->sole();
        $this->assertAuthenticatedAs($user, 'web');
        self::assertSame('Persona de prueba', $user->name);
        self::assertSame('activa', $user->estado_cuenta);
        self::assertNull($user->email_verified_at);
        self::assertTrue(Hash::check('UnaClaveSegura!123', $user->password));
        self::assertNotSame($id, session()->getId());
        self::assertNotSame($token, session()->token());
        self::assertSame([$user->id], $eventos);
        self::assertSame(0, $user->roles()->count());
        self::assertSame(0, $user->instituciones()->count());
        self::assertSame(today()->toDateString(), $user->participante->fecha_alta->toDateString());
        self::assertNull($user->participante->telefono);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $user->email, 'password' => 'UnaClaveSegura!123'])->assertRedirect('/mi-cuenta');
        $this->get('/mi-cuenta')->assertOk()->assertSee('Verificar mi correo');
        $this->get('/email/verify')->assertOk()->assertSee($user->email)->assertSee('Reenviar correo');
    }

    public static function datosInvalidos(): array
    {
        return [
            'nombre vacío' => [['name' => '  '], 'name'],
            'nombre arreglo' => [['name' => ['x']], 'name'],
            'nombre largo' => [['name' => str_repeat('a', 256)], 'name'],
            'correo inválido' => [['email' => 'incorrecto'], 'email'],
            'correo arreglo' => [['email' => ['x']], 'email'],
            'correo largo' => [['email' => str_repeat('a', 250).'@example.test'], 'email'],
            'contraseña corta' => [['password' => 'Aa1!', 'password_confirmation' => 'Aa1!'], 'password'],
            'sin mayúsculas' => [['password' => 'unaclavesegura!123', 'password_confirmation' => 'unaclavesegura!123'], 'password'],
            'sin números' => [['password' => 'UnaClaveSegura!!!', 'password_confirmation' => 'UnaClaveSegura!!!'], 'password'],
            'sin símbolos' => [['password' => 'UnaClaveSegura123', 'password_confirmation' => 'UnaClaveSegura123'], 'password'],
            'sin minúsculas' => [['password' => 'UNACLAVESEGURA!123', 'password_confirmation' => 'UNACLAVESEGURA!123'], 'password'],
            'confirmación distinta' => [['password_confirmation' => 'Diferente!123'], 'password'],
            'confirmación ausente' => [['password_confirmation' => ''], 'password_confirmation'],
            'contraseña arreglo' => [['password' => ['x']], 'password'],
            'límite bcrypt' => [['password' => str_repeat('Á', 40).'aA1!'], 'password'],
        ];
    }

    #[DataProvider('datosInvalidos')]
    public function test_rechaza_datos_invalidos_sin_crear_cuenta(array $datos, string $campo): void
    {
        Notification::fake();
        $users = DB::table('users')->count();
        $perfiles = DB::table('participantes')->count();
        $this->from('/register')->post('/register', $this->datos($datos))
            ->assertRedirect('/register')->assertSessionHasErrors($campo)
            ->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->assertGuest('web');
        self::assertSame($users, DB::table('users')->count());
        self::assertSame($perfiles, DB::table('participantes')->count());
        Notification::assertNothingSent();
    }

    public function test_correo_duplicado_historico_y_eliminado_se_reserva_y_nombres_repetidos_se_permiten(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'Existente@Example.Test', 'name' => 'Persona de prueba']);
        $this->post('/register', $this->datos(['email' => ' EXISTENTE@example.test ']))->assertSessionHasErrors('email');
        $user->delete();
        $this->post('/register', $this->datos(['email' => 'existente@example.test']))->assertSessionHasErrors('email');
        $this->post('/register', $this->datos())->assertRedirect('/email/verify');
    }

    public function test_fallo_de_perfil_revierte_usuario_y_no_emite_registro(): void
    {
        Notification::fake();
        Event::fake([Registered::class]);
        DB::unprepared("CREATE TRIGGER fallo_perfil BEFORE INSERT ON participantes BEGIN SELECT RAISE(ABORT, 'fallo de prueba'); END");
        $this->post('/register', $this->datos())->assertStatus(500);
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.test']);
        $this->assertGuest('web');
        Event::assertNotDispatched(Registered::class);
        Notification::assertNothingSent();
    }

    public function test_restriccion_unica_maneja_colision_posterior_a_validacion(): void
    {
        $service = app(RegistroParticipanteService::class);
        $service->registrar('Persona', 'colision@example.test', 'UnaClaveSegura!123');
        try {
            $service->registrar('Otra', 'colision@example.test', 'UnaClaveSegura!123');
            self::fail('Debía rechazar el correo repetido.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('email', $error->errors());
        }
        self::assertSame(1, User::where('email', 'colision@example.test')->count());
    }

    public function test_notificacion_nativa_en_espanol_con_enlace_firmado_funcional_e_idempotente(): void
    {
        Notification::fake();
        Event::fake([Verified::class]);
        $this->post('/register', $this->datos())->assertRedirect('/email/verify');
        $user = User::where('email', 'nuevo@example.test')->sole();
        $url = '';
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user, &$url): bool {
            $mail = $notification->toMail($user);
            self::assertSame('Verifica tu correo en Aula Verde', $mail->subject);
            self::assertSame('Verificar correo electrónico', $mail->actionText);
            $url = $mail->actionUrl;

            return str_contains($url, 'signature=') && str_contains($url, 'expires=');
        });
        $this->get($url)->assertRedirect('/mi-cuenta');
        self::assertNotNull($user->fresh()->email_verified_at);
        self::assertSame('activa', $user->fresh()->estado_cuenta);
        self::assertSame(0, $user->roles()->count());
        $fecha = $user->fresh()->email_verified_at->toString();
        $this->get($url)->assertRedirect('/mi-cuenta');
        self::assertSame($fecha, $user->fresh()->email_verified_at->toString());
        Event::assertDispatchedTimes(Verified::class, 1);
        $this->get('/email/verify')->assertRedirect('/mi-cuenta');
        $this->post('/email/verification-notification')->assertRedirect('/mi-cuenta');
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        $this->get('/mi-cuenta')->assertOk()->assertSee('Tu correo electrónico está verificado.');
        $this->get('/_demo-tesis/estudiante/'.$this->institucion->id)->assertForbidden();
        $this->get('/_demo-tesis/privado/'.$this->institucion->id)->assertForbidden();
    }

    public function test_enlaces_alterados_ajenos_caducados_o_de_otro_correo_no_verifican(): void
    {
        $user = User::factory()->unverified()->create(['estado_cuenta' => 'activa']);
        $otro = User::factory()->unverified()->create(['estado_cuenta' => 'activa']);
        $this->actingAs($user);
        $this->get($this->enlace($user).'x')->assertForbidden();
        $this->get($this->enlace($otro))->assertForbidden();
        $this->get($this->enlace($user, ['hash' => sha1('otro@example.test')]))->assertForbidden();
        $url = $this->enlace($user);
        $user->forceFill(['email' => 'cambiado@example.test'])->save();
        $this->travel(61)->seconds();
        $this->get($url)->assertForbidden();
        $url = $this->enlace($user);
        $this->travel(61)->minutes();
        $this->get($url)->assertForbidden();
        self::assertNull($user->fresh()->email_verified_at);
        self::assertNull($otro->fresh()->email_verified_at);
    }

    public function test_verificacion_no_reactiva_cuentas_bloqueadas_y_logout_sigue_disponible(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['estado_cuenta' => 'activa']);
        $this->actingAs($user);
        $url = $this->enlace($user);
        $user->forceFill(['estado_cuenta' => 'suspendida'])->save();
        $this->get('/email/verify')->assertForbidden();
        $this->get($url)->assertForbidden();
        $this->post('/email/verification-notification')->assertForbidden();
        self::assertNull($user->fresh()->email_verified_at);
        self::assertSame('suspendida', $user->fresh()->estado_cuenta);
        Notification::assertNothingSent();
        $this->post('/logout')->assertRedirect('/');
    }

    public function test_acceso_academico_exige_verificacion_ademas_de_rol_e_institucion(): void
    {
        $user = User::findOrFail($this->participante->user_id);
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user);
        $path = '/_demo-tesis/estudiante/'.$this->institucion->id;
        $this->get($path)->assertRedirect('/email/verify');
        $this->getJson($path)->assertForbidden();
        $this->get($this->enlace($user))->assertRedirect('/mi-cuenta');
        $this->get($path)->assertOk();
        $this->get('/_demo-tesis/estudiante/'.$this->otraInstitucion->id)->assertForbidden();
    }

    public function test_reenvio_limitado_por_usuario_sin_usar_destinatario_del_navegador(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['estado_cuenta' => 'activa']);
        $this->actingAs($user);
        for ($i = 0; $i < 3; $i++) {
            $this->post('/email/verification-notification', ['email' => 'ajeno@example.test', 'id' => $this->admin->id])
                ->assertRedirect('/email/verify');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11']);
        $this->post('/email/verification-notification')->assertStatus(429)->assertHeader('Retry-After');
        Notification::assertSentToTimes($user, VerifyEmail::class, 3);
        Notification::assertNotSentTo($this->admin, VerifyEmail::class);
        $this->travel(61)->seconds();
        $this->post('/email/verification-notification')->assertRedirect('/email/verify');
        Notification::assertSentToTimes($user, VerifyEmail::class, 4);
    }

    public function test_limite_registro_cuenta_entradas_invalidas_y_se_recupera(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register')->assertSessionHasErrors(['name', 'email', 'password']);
        }
        $this->post('/register')->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        $this->post('/register')->assertSessionHasErrors('email');
    }

    public function test_rutas_autenticadas_invitados_y_baja_logica(): void
    {
        $user = User::factory()->unverified()->create(['estado_cuenta' => 'activa']);
        $url = $this->enlace($user);
        $this->get('/email/verify')->assertRedirect('/login');
        $this->get($url)->assertRedirect('/login');
        $this->post('/email/verification-notification')->assertRedirect('/login');
        $this->getJson('/email/verify')->assertUnauthorized();
        $this->actingAs($user);
        $this->get('/register')->assertRedirect('/mi-cuenta');
        $this->post('/register', $this->datos())->assertRedirect('/mi-cuenta');
        $user->delete();
        // El guard vuelve a obtener la cuenta para simular la siguiente petición.
        Auth::forgetGuards();
        $this->get($url)->assertRedirect('/login');
    }

    public function test_csrf_en_registro_y_reenvio(): void
    {
        Notification::fake();
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->get('/register')->assertOk();
        $this->post('/register', $this->datos())->assertStatus(419);
        $this->post('/register', $this->datos(['_token' => session()->token()]))->assertRedirect('/email/verify');
        $this->post('/email/verification-notification')->assertStatus(419);
        $this->post('/email/verification-notification', ['_token' => session()->token()])->assertRedirect('/email/verify');
    }

    public function test_fallo_de_correo_conserva_cuenta_y_permite_recuperar_envio(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('Fallo simulado'));
        $this->post('/register', $this->datos())->assertRedirect('/email/verify')
            ->assertSessionHas('status', 'Tu cuenta fue creada, pero no pudimos enviar el correo. Intenta reenviarlo desde esta pantalla.');
        $user = User::where('email', 'nuevo@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        self::assertNotNull($user->participante);
        self::assertNull($user->email_verified_at);
        Notification::fake();
        $this->post('/email/verification-notification')->assertRedirect('/email/verify');
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_correo_se_renderiza_y_se_entrega_al_transporte_aislado(): void
    {
        config(['mail.default' => 'array']);
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $transport->flush();
        $this->post('/register', $this->datos())->assertRedirect('/email/verify');
        self::assertCount(1, $transport->messages());
        $message = $transport->messages()->first()->getOriginalMessage();
        self::assertSame('nuevo@example.test', $message->getTo()[0]->getAddress());
        self::assertSame('Verifica tu correo en Aula Verde', $message->getSubject());
        self::assertStringContainsString('Verificar correo electrónico', $message->getHtmlBody());
        self::assertStringContainsString('/email/verify/', $message->getHtmlBody());
        self::assertStringContainsString('signature=', $message->getHtmlBody());
        $this->post('/email/verification-notification')->assertRedirect('/email/verify');
        self::assertCount(2, $transport->messages());
    }

    public function test_mensaje_de_seguridad_de_password_en_espanol(): void
    {
        $this->post('/register', $this->datos(['password' => 'UnaClaveSegura123', 'password_confirmation' => 'UnaClaveSegura123']))
            ->assertSessionHasErrors(['password' => 'La contraseña debe incluir un símbolo.']);
    }
}
