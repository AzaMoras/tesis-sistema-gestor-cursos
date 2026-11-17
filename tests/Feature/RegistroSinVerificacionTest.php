<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\Support\ServiceTestCase;

class RegistroSinVerificacionTest extends ServiceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['auth.verification.enabled' => false]);
    }

    private function datos(array $extra = []): array
    {
        return $extra + ['name' => 'Persona de prueba', 'email' => 'nuevo@example.test',
            'password' => 'UnaClaveSegura!123', 'password_confirmation' => 'UnaClaveSegura!123'];
    }

    public function test_registro_inmediato_y_login_sin_correo_ni_privilegios(): void
    {
        config(['mail.default' => 'array']);
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $transport->flush();
        $eventos = [];
        Event::listen(Registered::class, function (Registered $event) use (&$eventos): void {
            $eventos[] = $event->user->id;
        });
        $this->withSession(['url.intended' => 'https://externo.test']);
        $id = session()->getId();
        $token = session()->token();
        $this->post('/register', $this->datos(['email' => ' NUEVO@EXAMPLE.TEST ',
            'role' => 'administrador', 'roles' => ['administrador'], 'rol_id' => 1,
            'institution_id' => $this->institucion->id, 'institucion_id' => $this->institucion->id,
            'permissions' => ['*'], 'estado_cuenta' => 'suspendida',
            'email_verified_at' => now(), 'verification' => ['enabled' => true]]))
            ->assertRedirect('/mi-cuenta')->assertSessionMissing('url.intended');
        $user = User::where('email', 'nuevo@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        self::assertSame('activa', $user->estado_cuenta);
        self::assertNull($user->email_verified_at);
        self::assertNotNull($user->participante);
        self::assertSame(0, $user->roles()->count());
        self::assertSame(0, $user->instituciones()->count());
        self::assertSame([$user->id], $eventos);
        self::assertNotSame($id, session()->getId());
        self::assertNotSame($token, session()->token());
        self::assertCount(0, $transport->messages());
        $this->get('/mi-cuenta')->assertOk()->assertDontSee('Verificar mi correo')
            ->assertDontSee('pendiente de verificación')->assertDontSee('está verificado');
        $this->get('/_demo-tesis/estudiante/'.$this->institucion->id)->assertForbidden();
        $this->get('/_demo-tesis/privado/'.$this->institucion->id)->assertForbidden();
        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => ' NUEVO@example.test ', 'password' => 'UnaClaveSegura!123'])
            ->assertRedirect('/mi-cuenta');
        $this->assertAuthenticatedAs($user);
        self::assertNull($user->fresh()->email_verified_at);
    }

    public function test_interfaz_no_solicita_verificar_ni_muestra_enlaces_de_reenvio(): void
    {
        $this->get('/register')->assertOk()->assertDontSee('Después verificarás');
        $user = User::factory()->unverified()->create(['estado_cuenta' => 'activa']);
        $this->actingAs($user)->get('/mi-cuenta')->assertOk()
            ->assertDontSee('href="'.route('verification.notice').'"', false)
            ->assertDontSee('Verificar mi correo')->assertDontSee('pendiente de verificación');
    }

    public function test_rutas_evento_y_notificacion_directa_no_envian_ni_verifican_con_funcion_apagada(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['estado_cuenta' => 'activa']);
        $this->actingAs($user);
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->get('/email/verify')->assertNotFound();
        $this->get($url)->assertNotFound();
        $this->post('/email/verification-notification')->assertNotFound();
        event(new Registered($user));
        $user->sendEmailVerificationNotification();
        Notification::assertNothingSent();
        self::assertNull($user->fresh()->email_verified_at);
    }

    public function test_participante_sin_verificar_conserva_limites_de_rol_institucion_y_permiso(): void
    {
        $user = User::findOrFail($this->participante->user_id);
        $user->forceFill(['email_verified_at' => null])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/mi-cuenta');
        $this->get('/_demo-tesis/estudiante/'.$this->institucion->id)->assertOk();
        $this->get('/_demo-tesis/estudiante/'.$this->otraInstitucion->id)->assertForbidden();
        $this->get('/_demo-tesis/privado/'.$this->institucion->id)->assertForbidden();

        Route::get('/_test/consulta/{institucion}', fn () => response('Autorizado'))
            ->middleware(['web', 'auth:web', 'cuenta.activa', 'verified', 'participante', 'permiso:cursos.consultar']);
        $this->get('/_test/consulta/'.$this->institucion->id)->assertForbidden();
        $this->grant($user, 'cursos.consultar', $this->otraInstitucion, 'coordinador');
        $this->get('/_test/consulta/'.$this->institucion->id)->assertForbidden();
        $this->grant($user, 'cursos.consultar', $this->institucion, 'participante');
        $this->get('/_test/consulta/'.$this->institucion->id)->assertOk();
        self::assertNull($user->fresh()->email_verified_at);

        $user->forceFill(['estado_cuenta' => 'suspendida'])->save();
        Auth::forgetGuards();
        $this->get('/_demo-tesis/estudiante/'.$this->institucion->id)->assertForbidden();
        $this->post('/logout')->assertRedirect('/');
        $this->get('/_demo-tesis/estudiante/'.$this->institucion->id)->assertRedirect('/login');
    }

    public function test_validacion_correo_y_password_siguen_aplicando_sin_verificacion(): void
    {
        Notification::fake();
        $this->post('/register', $this->datos(['email' => 'incorrecto']))->assertSessionHasErrors('email');
        $this->post('/register', $this->datos(['password_confirmation' => 'otra']))->assertSessionHasErrors('password');
        $this->post('/register', $this->datos(['email' => ' '.strtoupper($this->admin->email).' ']))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.test']);
        Notification::assertNothingSent();
    }

    public function test_registro_sin_verificacion_conserva_limite_por_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register')->assertSessionHasErrors('email');
        }
        $this->post('/register', $this->datos())->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.test']);
    }

    public function test_rehabilitar_verificacion_restaura_aviso_barrera_y_envio_nativo(): void
    {
        Notification::fake();
        $user = User::findOrFail($this->participante->user_id);
        $user->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($user);
        $path = '/_demo-tesis/estudiante/'.$this->institucion->id;
        $this->get($path)->assertOk();
        config(['auth.verification.enabled' => true]);
        $this->get('/mi-cuenta')->assertOk()->assertSee('Verificar mi correo');
        $this->get($path)->assertRedirect('/email/verify');
        $this->get('/email/verify')->assertOk();
        $this->post('/email/verification-notification')->assertRedirect('/email/verify');
        Notification::assertSentTo($user, VerifyEmail::class);
        self::assertNull($user->fresh()->email_verified_at);
        config(['auth.verification.enabled' => false]);
        $this->get($path)->assertOk();
        $this->post('/email/verification-notification')->assertNotFound();
    }
}
