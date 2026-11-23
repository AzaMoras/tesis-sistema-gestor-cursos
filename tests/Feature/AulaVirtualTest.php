<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\Institucion;
use App\Models\MaterialCurso;
use App\Models\ModuloCurso;
use App\Models\Participante;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\RolPermiso;
use App\Models\User;
use App\Models\UsuarioRol;
use App\Services\InscripcionService;
use Illuminate\Support\Facades\Schema;
use Tests\Support\InscripcionPublicaTestCase;

class AulaVirtualTest extends InscripcionPublicaTestCase
{
    private ModuloCurso $modulo;

    private MaterialCurso $material;

    private Inscripcion $inscripcion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inscripcion = app(InscripcionService::class)->inscribirGratuita($this->usuario, $this->curso->id, $this->grupo->id);
        $this->modulo = $this->record(ModuloCurso::class, ['curso_id' => $this->curso->id, 'titulo' => 'Fundamentos del hardware', 'descripcion' => 'Identifica los componentes.', 'orden' => 10, 'obligatorio' => true]);
        $this->material = $this->record(MaterialCurso::class, ['modulo_id' => $this->modulo->id, 'titulo' => 'Material privado publicado', 'tipo' => 'enlace', 'url_externa' => 'https://www.wikipedia.org/', 'orden' => 10, 'publicado' => true]);
    }

    private function aula(?int $id = null): string
    {
        return '/mis-cursos/'.($id ?? $this->inscripcion->id).'/aula';
    }

    public function test_inscrito_confirmado_accede_a_su_aula_real_desde_mis_cursos(): void
    {
        $this->actingAs($this->usuario)->get('/mis-cursos')->assertOk()->assertSee('Entrar al aula')->assertSee($this->aula(), false)->assertSee('19/11/2026');
        $this->get($this->aula())->assertOk()->assertSee('Hardware introductorio')->assertSee('Institución de prueba')
            ->assertSee('Fundamentos del hardware')->assertSee('Material privado publicado')->assertSee('vigencia académica está pendiente')
            ->assertSee('target="_blank"', false)->assertSee('rel="noopener noreferrer"', false)->assertSee('referrerpolicy="no-referrer"', false)
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('100%');
        $this->assertDatabaseCount('inscripciones', 1);
        self::assertNull($this->inscripcion->fresh()->acceso_inicia_at);
    }

    public function test_visitante_no_puede_entrar_y_usuario_sin_inscripcion_no_tiene_acceso(): void
    {
        $this->get($this->aula())->assertRedirect('/login');
        $otro = $this->nuevoParticipante();
        $this->actingAs($otro)->get($this->aula())->assertNotFound()->assertDontSee('Material privado publicado');
        $this->get('/mis-cursos')->assertOk()->assertDontSee('Entrar al aula')->assertSee('Todavía no tienes inscripciones');
        $this->get($this->aula(999999))->assertNotFound();
    }

    public function test_cancelada_pendiente_o_fecha_inconsistente_no_conceden_acceso_y_conservan_historial(): void
    {
        $this->actingAs($this->usuario);
        foreach ([['estado' => 'cancelada'], ['estado' => 'pendiente_pago'], ['estado' => 'confirmada', 'fecha_inscripcion' => null],
            ['estado' => 'confirmada', 'fecha_inscripcion' => now()->addDay()], ['estado' => 'confirmada', 'fecha_inscripcion' => now(), 'fecha_cancelacion' => now()]] as $cambios) {
            $this->inscripcion->forceFill($cambios)->save();
            $this->get($this->aula())->assertForbidden()->assertSee('inscripción confirmada y no cancelada')->assertDontSee('Material privado publicado');
            $this->get('/mis-cursos')->assertOk()->assertDontSee('Entrar al aula')->assertSee('Hardware introductorio');
        }
        $this->assertDatabaseCount('inscripciones', 1);
    }

    public function test_cuenta_o_perfil_inactivos_no_conceden_acceso(): void
    {
        $this->usuario->forceFill(['estado_cuenta' => 'inactiva'])->save();
        $this->actingAs($this->usuario)->get($this->aula())->assertForbidden()->assertDontSee('Material privado publicado');
        $this->usuario->forceFill(['estado_cuenta' => 'activa'])->save();
        $this->usuario->participante()->firstOrFail()->delete();
        $this->get($this->aula())->assertForbidden();
    }

    public function test_institucion_inactiva_y_recursos_eliminados_no_conceden_acceso(): void
    {
        $this->actingAs($this->usuario);
        $this->institucion->forceFill(['estado' => 'inactiva'])->save();
        $this->get($this->aula())->assertForbidden()->assertSee('institución no están disponibles');
        $this->institucion->forceFill(['estado' => 'activa'])->save();
        $this->grupo->delete();
        $this->get($this->aula())->assertForbidden()->assertDontSee('Material privado publicado');
        $this->get('/mis-cursos')->assertOk()->assertSee('Hardware introductorio')->assertDontSee('Entrar al aula');
    }

    public function test_cadena_institucional_incoherente_y_rol_revocado_impiden_acceso(): void
    {
        $this->actingAs($this->usuario);
        $ajena = $this->record(Institucion::class, ['nombre' => 'Ajena', 'slug' => 'ajena', 'estado' => 'activa']);
        $this->categoria->forceFill(['institucion_id' => $ajena->id])->save();
        $this->get($this->aula())->assertForbidden()->assertDontSee('Material privado publicado');
        $this->get('/mis-cursos')->assertOk()->assertDontSee('Entrar al aula');
        $this->categoria->forceFill(['institucion_id' => $this->institucion->id])->save();
        $this->usuario->rolesEnInstitucion($this->institucion->id)->detach();
        $this->get($this->aula())->assertForbidden();
    }

    public function test_administrador_no_puede_usar_su_rol_para_abrir_inscripcion_ajena(): void
    {
        $otro = User::factory()->create(['estado_cuenta' => 'activa']);
        $rol = $this->record(Rol::class, ['clave' => 'administrador', 'nombre' => 'Administrador']);
        $this->record(UsuarioRol::class, ['user_id' => $otro->id, 'rol_id' => $rol->id, 'institucion_id' => $this->institucion->id]);
        $this->record(Participante::class, ['user_id' => $otro->id, 'fecha_alta' => today()]);
        $this->actingAs($otro)->get($this->aula())->assertNotFound();
    }

    public function test_oculta_borradores_recursos_inseguros_archivos_y_tipos_historicos(): void
    {
        foreach ([['titulo' => 'Borrador secreto', 'publicado' => false], ['titulo' => 'Enlace peligroso', 'url_externa' => 'javascript:alert(1)'],
            ['titulo' => 'Archivo privado', 'ruta_archivo' => 'privado/documento.pdf'], ['titulo' => 'Tipo no habilitado', 'tipo' => 'html']] as $i => $cambios) {
            $this->record(MaterialCurso::class, $cambios + ['modulo_id' => $this->modulo->id, 'titulo' => 'Recurso', 'tipo' => 'enlace', 'url_externa' => 'https://example.org/', 'orden' => 20 + $i, 'publicado' => true]);
        }
        $this->actingAs($this->usuario)->get($this->aula())->assertOk()->assertSee('Material privado publicado')
            ->assertDontSee('Borrador secreto')->assertDontSee('Enlace peligroso')->assertDontSee('javascript:', false)->assertDontSee('Archivo privado')->assertDontSee('privado/documento.pdf')->assertDontSee('Tipo no habilitado');
    }

    public function test_materiales_y_modulos_eliminados_no_se_muestran(): void
    {
        $this->material->delete();
        $this->actingAs($this->usuario)->get($this->aula())->assertOk()->assertDontSee('Material privado publicado')->assertSee('todavía no tiene materiales publicados');
        $this->modulo->delete();
        $this->get($this->aula())->assertOk()->assertDontSee('Fundamentos del hardware')->assertSee('El contenido está en preparación');
    }

    public function test_presenta_modulos_y_materiales_ordenados_y_estados_vacios(): void
    {
        $this->record(ModuloCurso::class, ['curso_id' => $this->curso->id, 'titulo' => 'Módulo anterior vacío', 'orden' => 5, 'obligatorio' => false]);
        $this->record(MaterialCurso::class, ['modulo_id' => $this->modulo->id, 'titulo' => 'Primer recurso', 'tipo' => 'video', 'url_externa' => 'https://example.org/video', 'orden' => 5, 'publicado' => true]);
        $this->actingAs($this->usuario)->get($this->aula())->assertOk()->assertSeeInOrder(['Módulo anterior vacío', 'Fundamentos del hardware'])
            ->assertSeeInOrder(['Primer recurso', 'Material privado publicado'])->assertSee('todavía no tiene materiales publicados');
    }

    public function test_curso_publico_no_expone_modulos_ni_materiales_privados(): void
    {
        $this->get('/cursos/'.$this->curso->id)->assertOk()->assertDontSee('Fundamentos del hardware')->assertDontSee('Material privado publicado')->assertDontSee('https://www.wikipedia.org/', false);
        $this->get('/cursos')->assertOk()->assertDontSee('Material privado publicado');
    }

    public function test_vigencia_configurada_sin_activacion_restringe_acceso_sin_crear_fechas(): void
    {
        $this->edicion->forceFill(['tipo_vigencia' => 'con_vencimiento', 'duracion_acceso_dias' => 30])->save();
        $this->actingAs($this->usuario)->get($this->aula())->assertForbidden()->assertSee('no está activado o ya venció')->assertDontSee('Material privado publicado');
        $this->get('/mis-cursos')->assertOk()->assertDontSee('Entrar al aula');
        self::assertNull($this->inscripcion->fresh()->acceso_inicia_at);
    }

    public function test_vigencia_activada_valida_y_vencimiento_se_verifican_en_cada_consulta(): void
    {
        $this->edicion->forceFill(['tipo_vigencia' => 'con_vencimiento', 'duracion_acceso_dias' => 30])->save();
        $this->inscripcion->forceFill(['acceso_inicia_at' => now(), 'acceso_vence_at' => now()->addDay()])->save();
        $this->actingAs($this->usuario)->get($this->aula())->assertOk()->assertSee('20/11/2026 12:00 UTC');
        $this->travelTo(now()->addDay());
        $this->get($this->aula())->assertForbidden()->assertDontSee('Material privado publicado');
        $this->get('/mis-cursos')->assertOk()->assertDontSee('Entrar al aula');
        $this->assertDatabaseCount('inscripciones', 1);
    }

    public function test_vigencia_futura_o_datos_parciales_no_se_interpretan_como_acceso_indefinido(): void
    {
        $this->inscripcion->forceFill(['acceso_inicia_at' => now()->addHour(), 'acceso_vence_at' => now()->addDay()])->save();
        $this->actingAs($this->usuario)->get($this->aula())->assertForbidden();
        $this->inscripcion->forceFill(['acceso_inicia_at' => null])->save();
        $this->get($this->aula())->assertForbidden();
    }

    public function test_sin_vencimiento_solo_se_anuncia_cuando_existe_activacion_explicita(): void
    {
        $this->edicion->forceFill(['tipo_vigencia' => 'sin_vencimiento'])->save();
        $this->actingAs($this->usuario)->get($this->aula())->assertForbidden();
        $this->inscripcion->forceFill(['acceso_inicia_at' => now()])->save();
        $this->get($this->aula())->assertOk()->assertSee('según la activación registrada');
    }

    public function test_sin_columnas_de_vigencia_consulta_provisional_no_escribe_ni_falla(): void
    {
        $migracion = require database_path('migrations/2026_11_10_000001_add_vigencia_to_ediciones_and_inscripciones.php');
        $migracion->down(); // Exclusivamente SQLite :memory:, verificado antes de RefreshDatabase.
        self::assertFalse(Schema::hasColumn('ediciones', 'tipo_vigencia'));
        $this->actingAs($this->usuario)->get('/mis-cursos')->assertOk()->assertSee('Entrar al aula');
        $this->get($this->aula())->assertOk()->assertSee('no implica acceso indefinido')->assertSee('Material privado publicado');
        $this->assertDatabaseCount('inscripciones', 1);
    }

    public function test_esquema_de_vigencia_parcial_y_configurado_bloquea_el_acceso(): void
    {
        $this->edicion->forceFill(['tipo_vigencia' => 'con_vencimiento', 'duracion_acceso_dias' => 30])->save();
        Schema::table('inscripciones', fn ($tabla) => $tabla->dropColumn('acceso_inicia_at'));
        $this->actingAs($this->usuario)->get($this->aula())->assertForbidden()->assertSee('configuración de vigencia está incompleta');
    }

    public function test_verificacion_opcional_se_conserva_en_la_ruta_privada(): void
    {
        config(['auth.verification.enabled' => true]);
        $this->actingAs($this->usuario)->get($this->aula())->assertRedirect('/email/verify');
        config(['auth.verification.enabled' => false]);
        $this->get($this->aula())->assertOk();
        self::assertNull($this->usuario->fresh()->email_verified_at);
    }

    public function test_formacion_nueva_no_cambia_el_flujo_gratuito_y_cada_curso_muestra_solo_su_contenido(): void
    {
        $nuevo = $this->nuevoParticipante();
        $this->actingAs($nuevo)->post($this->destino(), ['confirmacion' => '1'])->assertRedirect('/mis-cursos');
        $inscripcion = $nuevo->participante->inscripciones()->firstOrFail();
        $this->get('/mis-cursos')->assertOk()->assertSee('Entrar al aula');
        $this->get($this->aula($inscripcion->id))->assertOk()->assertSee('Fundamentos del hardware');
        $this->assertDatabaseCount('inscripciones', 2);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_flujo_completo_administrador_publica_y_participante_consulta_sin_ver_borradores_ni_otro_curso(): void
    {
        $admin = User::factory()->create(['estado_cuenta' => 'activa']);
        $rol = $this->record(Rol::class, ['clave' => 'administrador', 'nombre' => 'Administrador']);
        $permiso = $this->record(Permiso::class, ['clave' => 'cursos.gestionar']);
        $this->record(RolPermiso::class, ['rol_id' => $rol->id, 'permiso_id' => $permiso->id]);
        $this->record(UsuarioRol::class, ['user_id' => $admin->id, 'rol_id' => $rol->id, 'institucion_id' => $this->institucion->id]);
        $base = '/gestion/'.$this->institucion->id.'/cursos/'.$this->curso->id.'/contenido/modulos';
        $this->actingAs($admin)->post($base, ['titulo' => 'Módulo creado desde gestión', 'descripcion' => 'Contenido real', 'orden' => 20, 'obligatorio' => '1'])->assertRedirect();
        $modulo = ModuloCurso::latest('id')->firstOrFail();
        $this->post($base.'/'.$modulo->id.'/materiales', ['titulo' => 'Nuevo video educativo', 'tipo' => 'video', 'url_externa' => 'https://example.org/video', 'orden' => 1])->assertRedirect();
        $material = MaterialCurso::latest('id')->firstOrFail();
        $this->actingAs($this->usuario)->get($this->aula())->assertOk()->assertSee('Módulo creado desde gestión')->assertDontSee('Nuevo video educativo');
        $this->actingAs($admin)->post($base.'/'.$modulo->id.'/materiales/'.$material->id.'/publicar')->assertRedirect();
        $otroCurso = $this->record(Curso::class, ['institucion_id' => $this->institucion->id, 'categoria_id' => $this->categoria->id, 'titulo' => 'Otro curso', 'descripcion' => 'Otro', 'estado' => 'publicado']);
        $otroModulo = $this->record(ModuloCurso::class, ['curso_id' => $otroCurso->id, 'titulo' => 'Contenido de otro curso', 'orden' => 1]);
        $this->record(MaterialCurso::class, ['modulo_id' => $otroModulo->id, 'titulo' => 'Recurso ajeno publicado', 'tipo' => 'enlace', 'url_externa' => 'https://example.org/ajeno', 'orden' => 1, 'publicado' => true]);
        $this->actingAs($this->usuario)->get($this->aula())->assertOk()->assertSee('Nuevo video educativo')->assertDontSee('Recurso ajeno publicado')->assertDontSee('Contenido de otro curso');
        $this->get('/cursos/'.$this->curso->id)->assertOk()->assertDontSee('Nuevo video educativo');
        $this->actingAs($admin)->post($base.'/'.$modulo->id.'/materiales/'.$material->id.'/ocultar')->assertRedirect();
        $this->actingAs($this->usuario)->get($this->aula())->assertOk()->assertDontSee('Nuevo video educativo');
    }
}
