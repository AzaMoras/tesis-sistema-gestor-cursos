<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Institucion;
use App\Models\Requisito;
use App\Models\Sesion;
use App\Models\User;
use App\Services\InscripcionService;
use App\Services\Soporte\ReglasOperacion;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\InscripcionPublicaTestCase;

class InscripcionGratuitaTest extends InscripcionPublicaTestCase
{
    private function inscribir(array $datos = ['confirmacion' => '1'])
    {
        return $this->actingAs($this->usuario)->from('/cursos/'.$this->curso->id)->post($this->destino(), $datos);
    }

    public function test_visitante_consulta_curso_pero_debe_autenticarse_para_confirmar(): void
    {
        $this->get('/cursos/'.$this->curso->id)->assertOk()->assertSee('Iniciar sesión para inscribirme')->assertSee('Inscripción gratuita disponible');
        $this->post($this->destino(), ['confirmacion' => '1'])->assertRedirect('/login');
        $this->get('/mis-cursos')->assertRedirect('/login');
        $this->get($this->destino())->assertMethodNotAllowed();
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertDatabaseCount('usuario_rol', 0);
    }

    public function test_registro_desde_curso_regresa_sin_privilegios_y_luego_inscribe(): void
    {
        $this->get('/cursos/'.$this->curso->id.'/acceso?destino=register')->assertRedirect('/register');
        $this->post('/register', ['name' => 'Nueva persona', 'email' => 'nueva@example.test', 'password' => 'OtraClaveSegura!456', 'password_confirmation' => 'OtraClaveSegura!456'])
            ->assertRedirect(route('cursos.show', $this->curso->id).'#ediciones-titulo');
        $user = User::where('email', 'nueva@example.test')->sole();
        self::assertNull($user->email_verified_at);
        $this->assertDatabaseCount('usuario_rol', 0);
        $this->assertDatabaseCount('inscripciones', 0);
        $this->post($this->destino(), ['confirmacion' => '1'])->assertRedirect('/mis-cursos')->assertSessionHas('status', 'Tu inscripción gratuita fue confirmada correctamente.');
        $this->assertDatabaseHas('inscripciones', ['participante_id' => $user->participante->id, 'grupo_id' => $this->grupo->id, 'estado' => 'confirmada', 'precio_acordado' => 0]);
        $this->assertDatabaseHas('usuario_rol', ['user_id' => $user->id, 'rol_id' => $this->rol->id, 'institucion_id' => $this->institucion->id, 'asignado_por' => $user->id]);
        $this->get('/mis-cursos')->assertOk()->assertSee($this->curso->titulo)->assertSee('confirmada');
        self::assertNull($user->fresh()->email_verified_at);
    }

    public function test_login_regresa_solo_al_curso_guardado_por_el_servidor_y_preserva_intento_fallido(): void
    {
        $this->get('/cursos/'.$this->curso->id.'/acceso')->assertRedirect('/login');
        $this->post('/login', ['email' => $this->usuario->email, 'password' => 'Incorrecta'])->assertSessionHasErrors('email');
        $this->withSession(['url.intended' => 'https://externo.example.test']);
        $this->post('/login', ['email' => $this->usuario->email, 'password' => 'UnaClaveSegura!123', 'redirect' => 'https://externo.example.test'])
            ->assertRedirect(route('cursos.show', $this->curso->id).'#ediciones-titulo')->assertSessionMissing('inscripcion.curso_id')->assertSessionMissing('url.intended');
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_retorno_rechaza_destinos_externos_y_cursos_ya_no_publicos(): void
    {
        $this->getJson('/cursos/'.$this->curso->id.'/acceso?destino=https://externo.test')->assertUnprocessable();
        $this->get('/cursos/'.$this->curso->id.'/acceso')->assertRedirect('/login');
        $this->curso->forceFill(['estado' => 'borrador'])->save();
        $this->post('/login', ['email' => $this->usuario->email, 'password' => 'UnaClaveSegura!123'])->assertRedirect('/mi-cuenta');
    }

    public function test_confirmacion_explicita_obligatoria_y_campos_manipulados_rechazados(): void
    {
        $this->inscribir([])->assertSessionHasErrors('confirmacion');
        $this->inscribir(['confirmacion' => '0'])->assertSessionHasErrors('confirmacion');
        foreach (['participante_id', 'user_id', 'institucion_id', 'rol_id', 'precio', 'estado', 'cupo_maximo', 'grupo_id'] as $campo) {
            $this->inscribir(['confirmacion' => '1', $campo => '999'])->assertSessionHasErrors('solicitud');
        }
        $this->assertDatabaseCount('usuario_rol', 0);
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_confirmacion_valida_es_real_y_mis_cursos_muestra_relaciones(): void
    {
        $this->inscribir()->assertRedirect('/mis-cursos');
        $this->get('/mis-cursos')->assertOk()->assertSee($this->curso->titulo)->assertSee($this->institucion->nombre)
            ->assertSee($this->edicion->clave)->assertSee($this->grupo->codigo)->assertSee('Autogestionado')->assertSee('19/11/2026 12:00')->assertSee('confirmada');
        $this->get('/cursos/'.$this->curso->id)->assertOk()->assertSee('Ya tienes una inscripción en este grupo')->assertDontSee('name="confirmacion"', false);
        $this->get('/mi-cuenta')->assertOk()->assertSee('href="'.route('mis-cursos').'"', false)->assertDontSee('Panel de gestión');
        $this->get('/gestion/'.$this->institucion->id.'/cursos')->assertForbidden();
        $this->assertDatabaseCount('roles', 1);
        $this->assertDatabaseCount('permisos', 0);
    }

    public function test_clic_repetido_rechaza_duplicado_sin_duplicar_asignacion_o_bitacora(): void
    {
        $this->inscribir()->assertRedirect('/mis-cursos');
        $this->inscribir()->assertSessionHasErrors('inscripcion');
        $this->assertDatabaseCount('inscripciones', 1);
        $this->assertDatabaseCount('usuario_rol', 1);
        $this->assertDatabaseCount('bitacora', 2);
    }

    public function test_rol_ausente_falla_sin_crearlo_y_explica_el_bloqueo(): void
    {
        $this->rol->delete();
        $this->get('/cursos/'.$this->curso->id)->assertOk()->assertSee('todavía no ha habilitado las inscripciones gratuitas');
        $this->inscribir()->assertSessionHasErrors('inscripcion');
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('usuario_rol', 0);
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_cupo_completo_se_revalida_y_no_deja_pertenencia_huerfana(): void
    {
        $this->grupo->forceFill(['cupo_maximo' => 1])->save();
        $this->actingAs($this->usuario)->get('/cursos/'.$this->curso->id)->assertSee('Inscripción gratuita disponible');
        app(InscripcionService::class)->inscribirGratuita($this->nuevoParticipante(), $this->curso->id, $this->grupo->id);
        $this->inscribir()->assertSessionHasErrors('cupo')->assertSessionMissing('status');
        $this->get('/cursos/'.$this->curso->id)->assertOk()->assertSee('El grupo ya no tiene lugares disponibles.');
        $this->assertDatabaseMissing('usuario_rol', ['user_id' => $this->usuario->id]);
        $this->assertDatabaseCount('inscripciones', 1);
    }

    public function test_cupo_cero_y_nulo_no_admiten_inscripcion(): void
    {
        foreach ([0, null] as $cupo) {
            $this->grupo->forceFill(['cupo_maximo' => $cupo])->save();
            $this->inscribir()->assertSessionHasErrors('cupo');
        }
        $this->assertDatabaseCount('usuario_rol', 0);
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_curso_edicion_y_grupo_cerrados_no_admiten_inscripcion(): void
    {
        $this->curso->forceFill(['estado' => 'borrador'])->save();
        $this->inscribir()->assertNotFound();
        $this->curso->forceFill(['estado' => 'publicado'])->save();
        $this->edicion->forceFill(['estado' => 'cerrada'])->save();
        $this->inscribir()->assertSessionHasErrors('edicion');
        $this->edicion->forceFill(['estado' => 'abierta'])->save();
        $this->grupo->forceFill(['estado' => 'cerrada'])->save();
        $this->inscribir()->assertSessionHasErrors('grupo');
        $this->assertDatabaseCount('usuario_rol', 0);
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_plazo_cerrado_fechas_invalidas_y_edicion_terminada_se_rechazan(): void
    {
        foreach ([['cierre_inscripciones_at' => now()], ['cierre_inscripciones_at' => null, 'fecha_inicio' => '2026-12-30'],
            ['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-11-18']] as $datos) {
            $this->edicion->forceFill($datos)->save();
            $this->inscribir()->assertSessionHasErrors('edicion');
        }
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_precio_no_cero_no_crea_reservas_pagos_ni_inscripciones(): void
    {
        foreach (['0.01', '100.00'] as $precio) {
            $this->edicion->forceFill(['precio' => $precio])->save();
            $this->inscribir()->assertSessionHasErrors('precio');
            $this->get('/cursos/'.$this->curso->id)->assertSee('Esta edición es de pago.');
        }
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertDatabaseCount('pagos', 0);
        $this->assertDatabaseCount('usuario_rol', 0);
    }

    public function test_requisitos_no_validados_impiden_inscripcion_sin_simular_su_cumplimiento(): void
    {
        $requisito = $this->record(Requisito::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'Conocimientos previos']);
        DB::table('curso_requisito')->insert(['curso_id' => $this->curso->id, 'requisito_id' => $requisito->id, 'obligatorio' => true]);
        $this->inscribir()->assertSessionHasErrors('requisitos');
        $this->get('/cursos/'.$this->curso->id)->assertOk()->assertSee('requisitos obligatorios pendientes');
        $this->assertDatabaseCount('participante_requisito', 0);
        $this->assertDatabaseCount('usuario_rol', 0);
    }

    public function test_politica_pendiente_es_error_comprensible_y_no_500(): void
    {
        $this->app->instance(ReglasOperacion::class, new ReglasOperacion(apertura: config('operacion.apertura'), cupoNuloIlimitado: false));
        $this->inscribir()->assertRedirect()->assertSessionHasErrors('inscripcion')->assertSessionHasInput('confirmacion', true);
        $this->get('/cursos/'.$this->curso->id)->assertOk()->assertSee('completar una política de inscripción');
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertDatabaseCount('usuario_rol', 0);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_grupo_de_otro_curso_y_categoria_ajena_no_se_aceptan(): void
    {
        $otro = $this->record(Curso::class, ['institucion_id' => $this->institucion->id, 'categoria_id' => $this->categoria->id, 'titulo' => 'Otro', 'descripcion' => 'Otro', 'estado' => 'publicado']);
        $this->actingAs($this->usuario)->post('/cursos/'.$otro->id.'/grupos/'.$this->grupo->id.'/inscripcion', ['confirmacion' => '1'])->assertNotFound();
        $ajena = $this->record(Institucion::class, ['nombre' => 'Ajena', 'slug' => 'ajena']);
        $this->categoria->forceFill(['institucion_id' => $ajena->id])->save();
        $this->inscribir()->assertNotFound();
        $this->assertDatabaseCount('usuario_rol', 0);
    }

    public function test_institucion_inactiva_y_bajas_logicas_no_admiten_inscripcion(): void
    {
        $this->institucion->forceFill(['estado' => 'inactiva'])->save();
        $this->inscribir()->assertNotFound();
        $this->institucion->forceFill(['estado' => 'activa'])->save();
        $this->categoria->forceFill(['estado' => 'inactiva'])->save();
        $this->inscribir()->assertNotFound();
        $this->categoria->forceFill(['estado' => 'activa'])->save();
        foreach ([$this->grupo, $this->edicion, $this->curso, $this->categoria, $this->institucion] as $registro) {
            $registro->delete();
            $this->inscribir()->assertNotFound();
            $registro->restore();
        }
        $this->assertDatabaseCount('usuario_rol', 0);
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_cuenta_inactiva_perfil_ausente_y_perfil_eliminado_no_inscriben(): void
    {
        $this->usuario->forceFill(['estado_cuenta' => 'inactiva'])->save();
        $this->inscribir()->assertForbidden();
        $this->usuario->forceFill(['estado_cuenta' => 'activa'])->save();
        $this->usuario->participante->delete();
        $this->inscribir()->assertSessionHasErrors('participante');
        $this->get('/mis-cursos')->assertForbidden();
        $sinPerfil = User::factory()->create(['estado_cuenta' => 'activa']);
        $this->actingAs($sinPerfil)->post($this->destino(), ['confirmacion' => '1'])->assertSessionHasErrors('participante');
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_csrf_rechaza_confirmacion_sin_token_y_admite_token_valido(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($this->usuario)->get('/cursos/'.$this->curso->id)->assertOk()->assertSee('name="_token"', false);
        $this->post($this->destino(), ['confirmacion' => '1'])->assertStatus(419);
        $this->post($this->destino(), ['confirmacion' => '1', '_token' => 'incorrecto'])->assertStatus(419);
        $this->assertDatabaseCount('inscripciones', 0);
        $this->post($this->destino(), ['confirmacion' => '1', '_token' => session()->token()])->assertRedirect('/mis-cursos');
    }

    public function test_limite_de_frecuencia_y_configuracion_de_verificacion_se_respetan(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->inscribir([])->assertSessionHasErrors('confirmacion');
        }
        $this->inscribir()->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        config(['auth.verification.enabled' => true]);
        $this->inscribir()->assertRedirect(route('verification.notice'));
        $this->get('/mis-cursos')->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('inscripciones', 0);
        self::assertNull($this->usuario->fresh()->email_verified_at);
    }

    public function test_mis_cursos_aisla_propietario_incluso_con_parametros_manipulados(): void
    {
        $otro = $this->nuevoParticipante();
        app(InscripcionService::class)->inscribirGratuita($otro, $this->curso->id, $this->grupo->id);
        $this->actingAs($this->usuario)->get('/mis-cursos?participante_id='.$otro->participante->id.'&user_id='.$otro->id)
            ->assertOk()->assertSee('Todavía no tienes inscripciones')->assertDontSee($this->curso->titulo);
        $this->get('/mis-cursos/1')->assertNotFound();
        $this->assertDatabaseCount('inscripciones', 1);
    }

    public function test_historial_cancelado_y_padres_eliminados_se_conservan_sin_exponer_sesiones(): void
    {
        $this->inscribir()->assertRedirect('/mis-cursos');
        Inscripcion::query()->first()->forceFill(['estado' => 'cancelada', 'fecha_cancelacion' => now()])->save();
        $this->record(Sesion::class, ['grupo_id' => $this->grupo->id, 'titulo' => 'Sesión privada', 'inicio_at' => now(), 'fin_at' => now()->addHour(), 'enlace_virtual' => 'https://privado.example.test/secreto']);
        $this->grupo->delete();
        $this->edicion->delete();
        $this->curso->delete();
        $this->institucion->delete();
        $this->get('/mis-cursos')->assertOk()->assertSee($this->curso->titulo)->assertSee($this->institucion->nombre)->assertSee('cancelada')
            ->assertSee('Fecha de cancelación')->assertSee('Tu registro se conserva')->assertDontSee('privado.example.test')->assertDontSee('Sesión privada')
            ->assertDontSee('href="'.route('cursos.show', $this->curso->id).'"', false);
    }

    public function test_mis_cursos_escapa_contenido_y_paginas_son_privadas(): void
    {
        $this->curso->update(['titulo' => '<script>alert(1)</script>']);
        $this->inscribir()->assertRedirect('/mis-cursos');
        $this->get('/mis-cursos')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        Auth::logout();
        $this->getJson('/mis-cursos')->assertUnauthorized();
    }

    public function test_mis_cursos_pagina_el_historial_del_propietario(): void
    {
        app(InscripcionService::class)->inscribirGratuita($this->usuario, $this->curso->id, $this->grupo->id);
        for ($i = 2; $i <= 13; $i++) {
            $grupo = $this->record(Grupo::class, ['edicion_id' => $this->edicion->id, 'codigo' => sprintf('GR-%02d', $i), 'cupo_maximo' => 2, 'estado' => 'abierta']);
            app(InscripcionService::class)->inscribirGratuita($this->usuario, $this->curso->id, $grupo->id);
        }
        $this->actingAs($this->usuario)->get('/mis-cursos')->assertOk()->assertSee('Página 1 de 2')->assertSee('GR-13')->assertDontSee('GR-01');
        $this->get('/mis-cursos?page=2')->assertOk()->assertSee('Página 2 de 2')->assertSee('GR-01')->assertDontSee('GR-13');
        $this->assertDatabaseCount('usuario_rol', 1);
    }
}
