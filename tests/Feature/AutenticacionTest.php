<?php

namespace Tests\Feature;

use App\Models\Participante;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\Support\ServiceTestCase;

class AutenticacionTest extends ServiceTestCase
{
    private function login(User $user, array $extra = [])
    {
        return $this->post('/login', $extra + ['email' => $user->email, 'password' => 'password']);
    }

    private function enforceCsrf(): void
    {
        // Laravel omite CSRF en tests; solo se elimina ese bypass, sin cambiar BD/entorno.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_login_muestra_formulario_accesible_y_csrf(): void
    {
        $this->get('/login')->assertOk()->assertViewIs('auth.login')
            ->assertSee('name="_token"', false)->assertSee('autocomplete="current-password"', false)
            ->assertSee('for="email"', false)->assertSee('data-theme-toggle', false);
    }

    public function test_validaciones_no_conservan_password_ni_privilegios(): void
    {
        $this->from('/login')->post('/login', ['email' => 'incorrecto', 'password' => ['x'], 'role' => 'administrador'])
            ->assertRedirect('/login')->assertSessionHasErrors(['email', 'password'])
            ->assertSessionMissing('_old_input.password');
        $this->post('/login')->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest('web');
    }

    public function test_login_regenera_sesion_y_csrf_sin_asignar_privilegios(): void
    {
        $user = User::factory()->create(['email' => 'persona@example.test', 'estado_cuenta' => 'activa']);
        $this->withSession(['marca' => 'conservada', 'url.intended' => 'https://externo.test']);
        $id = session()->getId();
        $token = session()->token();
        $this->login($user, ['email' => '  PERSONA@example.test ', 'role' => 'administrador', 'institution_id' => $this->institucion->id,
            'permissions' => ['*'], 'estado_cuenta' => 'activa', 'email_verified_at' => now()->toString()])
            ->assertRedirect('/mi-cuenta')->assertSessionHas('marca', 'conservada')->assertSessionMissing('url.intended');
        $this->assertAuthenticatedAs($user, 'web');
        self::assertNotSame($id, session()->getId());
        self::assertNotSame($token, session()->token());
        self::assertSame(0, $user->roles()->count());
        $this->get('/mi-cuenta')->assertOk()->assertSee($user->name);
    }

    public function test_credenciales_incorrectas_e_inexistentes_tienen_mismo_error(): void
    {
        $this->login($this->admin, ['password' => 'incorrecta'])->assertSessionHasErrors('email');
        $message = session('errors')->get('email');
        $this->login($this->admin, ['email' => 'ausente@example.test'])->assertSessionHasErrors('email');
        self::assertSame($message, session('errors')->get('email'));
        $this->assertGuest('web');
    }

    public function test_cuenta_sin_institucion_y_correo_pendiente_ve_aviso_neutral(): void
    {
        config(['auth.verification.enabled' => false]);
        $user = User::factory()->unverified()->create(['estado_cuenta' => 'activa']);
        $this->login($user)->assertRedirect('/mi-cuenta');
        $this->get('/mi-cuenta')->assertOk()->assertDontSee('Tu correo está pendiente de verificación');
        $this->get('/_demo-tesis/estudiante')->assertForbidden();
        $this->get('/_demo-tesis/privado')->assertForbidden();
        self::assertSame(0, $user->roles()->count());
        self::assertNull($user->fresh()->email_verified_at);
    }

    public function test_cuenta_participante_sin_roles_muestra_solo_mis_cursos(): void
    {
        $user = User::factory()->create(['estado_cuenta' => 'activa']);
        $this->record(Participante::class, ['user_id' => $user->id, 'fecha_alta' => today()]);

        $this->actingAs($user, 'web')->get(route('cuenta'))->assertOk()
            ->assertSee('href="'.route('mis-cursos').'"', false)->assertSee('Mis cursos')
            ->assertDontSee('Panel de gestión');
        $this->get(route('mis-cursos'))->assertOk();
        $this->get(route('gestion.cursos.index', $this->institucion->id))->assertForbidden();
    }

    public function test_cuenta_administrativa_con_perfil_sin_inscripciones_muestra_solo_gestion(): void
    {
        $this->grant($this->admin, 'cursos.gestionar');
        $this->record(Participante::class, ['user_id' => $this->admin->id, 'fecha_alta' => today()]);

        $this->actingAs($this->admin, 'web')->get(route('cuenta'))->assertOk()
            ->assertSee('href="'.route('gestion.instituciones').'"', false)->assertSee('Panel de gestión')
            ->assertDontSee('href="'.route('mis-cursos').'"', false)->assertDontSee('Mis cursos');
        $this->get(route('gestion.instituciones'))->assertOk()
            ->assertSee('href="'.route('gestion.cursos.index', $this->institucion->id).'"', false)
            ->assertDontSee('href="'.route('gestion.cursos.index', $this->otraInstitucion->id).'"', false)
            ->assertSee('href="'.route('cuenta').'"', false);
        $this->get(route('gestion.cursos.index', $this->institucion->id))->assertOk();
        $this->get(route('mis-cursos'))->assertOk();
    }

    public function test_cuenta_de_personal_con_inscripcion_conserva_ambos_contextos(): void
    {
        $this->grant($this->admin, 'cursos.gestionar');
        $participante = $this->record(Participante::class, ['user_id' => $this->admin->id, 'fecha_alta' => today()]);
        $this->enrollment(['estado' => 'cancelada', 'fecha_cancelacion' => now()], $participante);

        $this->actingAs($this->admin, 'web')->get(route('cuenta'))->assertOk()
            ->assertSeeInOrder(['Como participante', 'Mis cursos', 'Como personal de gestión', 'Panel de gestión'])
            ->assertSee('href="'.route('mis-cursos').'"', false)
            ->assertSee('href="'.route('gestion.instituciones').'"', false);
        $this->get(route('mis-cursos'))->assertOk()->assertSee('Estado de inscripción: cancelada');
        $this->get(route('gestion.cursos.index', $this->institucion->id))->assertOk();
        $this->get(route('gestion.cursos.index', $this->otraInstitucion->id))->assertForbidden();
    }

    public function test_cuenta_de_personal_con_rol_participante_muestra_mis_cursos_sin_inscripciones(): void
    {
        $user = $this->participante->usuario;
        $this->grant($user, 'cursos.gestionar', role: 'coordinador');

        $this->actingAs($user, 'web')->get(route('cuenta'))->assertOk()
            ->assertSeeInOrder(['Como participante', 'Mis cursos', 'Como personal de gestión', 'Panel de gestión']);
        $this->get(route('mis-cursos'))->assertOk()->assertSee('Todavía no tienes inscripciones');
    }

    public function test_solo_activa_es_admitida_incluyendo_estados_desconocidos(): void
    {
        // El esquema es varchar: la lista positiva existente es exclusivamente activa.
        foreach (['pendiente', 'inactiva', 'suspendida', 'desconocido'] as $state) {
            $this->admin->forceFill(['estado_cuenta' => $state])->save();
            $this->login($this->admin)->assertSessionHasErrors(['email' => 'No fue posible iniciar sesión con esos datos.']);
            $this->assertGuest('web');
        }
    }

    public function test_cuenta_eliminada_no_puede_entrar(): void
    {
        $this->admin->delete();
        $this->login($this->admin)->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_limite_por_correo_normalizado_e_ip_y_recuperacion(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->login($this->admin, ['password' => 'incorrecta'])->assertSessionHasErrors('email');
        }
        $this->login($this->admin, ['email' => ' '.strtoupper($this->admin->email).' '])->assertStatus(429)
            ->assertHeader('Retry-After')->assertSee('Demasiados intentos');
        $this->assertGuest('web');
        $this->travel(61)->seconds();
        $this->login($this->admin)->assertRedirect('/mi-cuenta');
    }

    public function test_limite_por_ip_impide_rotacion_de_correos(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post('/login', ['email' => "ausente{$i}@example.test", 'password' => 'incorrecta'])->assertStatus(302);
        }
        $this->login($this->admin)->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
        $this->login($this->admin)->assertRedirect('/mi-cuenta');
    }

    public function test_logout_invalida_sesion_y_regenera_csrf_incluso_cuenta_bloqueada(): void
    {
        $this->login($this->admin)->assertRedirect('/mi-cuenta');
        $this->withSession(['dato_privado' => 'borrar']);
        $id = session()->getId();
        $token = session()->token();
        $this->admin->forceFill(['estado_cuenta' => 'pendiente'])->save();
        $this->post('/logout')->assertRedirect('/')->assertSessionMissing('dato_privado')
            ->assertSessionHas('status', 'Has cerrado sesión.');
        $this->assertGuest('web');
        self::assertNotSame($id, session()->getId());
        self::assertNotSame($token, session()->token());
        $this->get('/mi-cuenta')->assertRedirect('/login');
    }

    public function test_csrf_rechaza_login_y_logout_sin_token_y_acepta_token_valido(): void
    {
        $this->enforceCsrf();
        $this->get('/login')->assertOk();
        $this->login($this->admin)->assertStatus(419);
        $this->assertGuest('web');
        $this->login($this->admin, ['_token' => session()->token()])->assertRedirect('/mi-cuenta');
        $this->post('/logout')->assertStatus(419);
        $this->post('/logout', ['_token' => 'incorrecto'])->assertStatus(419);
        $this->assertAuthenticatedAs($this->admin, 'web');
        $this->post('/logout', ['_token' => session()->token()])->assertRedirect('/');
        $this->assertGuest('web');
    }

    public function test_guest_auth_y_metodo_logout(): void
    {
        $this->get('/mi-cuenta')->assertRedirect('/login');
        $this->getJson('/mi-cuenta')->assertUnauthorized();
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/logout')->assertStatus(405);
        $this->login($this->admin)->assertRedirect('/mi-cuenta');
        $this->get('/login')->assertRedirect('/mi-cuenta');
        $this->post('/login')->assertRedirect('/mi-cuenta');
        $this->get('/')->assertSee('Cerrar sesión')->assertSee('action="'.route('logout').'"', false);
    }

    public function test_cuenta_bloqueada_despues_del_login_no_entra_a_ruta_privada(): void
    {
        $this->login($this->admin)->assertRedirect('/mi-cuenta');
        $this->admin->forceFill(['estado_cuenta' => 'pendiente'])->save();
        // Una nueva petición real vuelve a recuperar el usuario desde la sesión.
        Auth::forgetGuards();
        $this->get('/mi-cuenta')->assertForbidden();
    }

    public function test_participante_no_entra_a_gestion_ni_institucion_ajena(): void
    {
        $user = User::findOrFail($this->participante->user_id);
        $this->login($user)->assertRedirect('/mi-cuenta');
        $this->get('/_demo-tesis/estudiante/'.$this->institucion->id)->assertOk();
        $this->get('/_demo-tesis/privado/'.$this->institucion->id)->assertForbidden();
        $this->get('/_demo-tesis/estudiante/'.$this->otraInstitucion->id)->assertForbidden();
    }

    public function test_roles_de_personal_son_institucionales(): void
    {
        foreach (['administrador', 'coordinador', 'recepcion', 'instructor'] as $role) {
            $user = $this->userWithRole($role);
            $this->login($user)->assertRedirect('/mi-cuenta');
            $this->get('/_demo-tesis/privado/'.$this->institucion->id)->assertOk();
            $this->get('/_demo-tesis/privado/'.$this->otraInstitucion->id)->assertForbidden();
            $this->post('/logout')->assertRedirect('/');
        }
    }

    public function test_permiso_en_http_no_se_hereda_de_otra_institucion_ni_del_rol_admin(): void
    {
        // Endpoint solo de prueba: no expone pagos ni módulos futuros en producción.
        Route::get('/_test/permiso/{institucion}', fn () => response('Autorizado'))
            ->middleware(['web', 'auth:web', 'cuenta.activa', 'permiso:pagos.verificar']);
        $this->login($this->admin)->assertRedirect('/mi-cuenta');
        $this->get('/_test/permiso/'.$this->institucion->id)->assertForbidden();
        $this->grant($this->admin, 'pagos.verificar', $this->otraInstitucion, 'coordinador');
        $this->get('/_test/permiso/'.$this->otraInstitucion->id)->assertOk();
        $this->get('/_test/permiso/'.$this->institucion->id)->assertForbidden();
        $this->grant($this->admin, 'pagos.verificar');
        $this->get('/_test/permiso/'.$this->institucion->id)->assertOk();
    }
}
