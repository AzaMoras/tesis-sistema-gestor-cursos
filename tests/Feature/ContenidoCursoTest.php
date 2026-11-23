<?php

namespace Tests\Feature;

use App\Models\MaterialCurso;
use App\Models\ModuloCurso;
use App\Services\ContenidoCursoService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Tests\Support\ServiceTestCase;

class ContenidoCursoTest extends ServiceTestCase
{
    private ModuloCurso $modulo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grant($this->admin, 'cursos.gestionar');
        $this->actingAs($this->admin);
        $this->modulo = $this->record(ModuloCurso::class, ['curso_id' => $this->grupo->edicion->curso_id, 'titulo' => 'Fundamentos del hardware', 'descripcion' => 'Componentes principales.', 'orden' => 10, 'obligatorio' => true]);
    }

    private function url(string $sufijo = ''): string
    {
        return '/gestion/'.$this->institucion->id.'/cursos/'.$this->grupo->edicion->curso_id.'/contenido'.$sufijo;
    }

    private function datosModulo(array $cambios = []): array
    {
        return $cambios + ['titulo' => 'Módulo nuevo', 'descripcion' => 'Descripción', 'orden' => 20, 'obligatorio' => '1'];
    }

    private function datosMaterial(array $cambios = []): array
    {
        return $cambios + ['titulo' => 'Recurso educativo', 'tipo' => 'enlace', 'url_externa' => 'https://www.wikipedia.org/', 'orden' => 10];
    }

    private function material(array $cambios = []): MaterialCurso
    {
        return $this->record(MaterialCurso::class, $cambios + ['modulo_id' => $this->modulo->id, 'publicado' => false] + $this->datosMaterial());
    }

    public function test_crear_editar_y_consultar_modulo_autorizado(): void
    {
        $this->get($this->url('/modulos/crear'))->assertOk()->assertSee('Crear módulo')->assertSee('name="_token"', false);
        $this->post($this->url('/modulos'), $this->datosModulo())->assertRedirect();
        $modulo = ModuloCurso::latest('id')->firstOrFail();
        self::assertSame($this->grupo->edicion->curso_id, $modulo->curso_id);
        $this->get($this->url('/modulos/'.$modulo->id.'/editar'))->assertOk()->assertSee('Módulo nuevo');
        $this->put($this->url('/modulos/'.$modulo->id), $this->datosModulo(['titulo' => 'Título corregido', 'orden' => 30, 'obligatorio' => '0']))->assertRedirect();
        self::assertSame(30, $modulo->fresh()->orden);
        self::assertFalse($modulo->fresh()->obligatorio);
        $this->get($this->url())->assertOk()->assertSeeInOrder(['Fundamentos del hardware', 'Título corregido']);
        $this->assertDatabaseCount('bitacora', 2);
    }

    public function test_material_borrador_revision_publicacion_y_ocultamiento_diferenciados(): void
    {
        $base = '/modulos/'.$this->modulo->id.'/materiales';
        $this->get($this->url($base.'/crear'))->assertOk()->assertSee('Guardar borrador');
        $this->post($this->url($base), $this->datosMaterial())->assertRedirect();
        $material = MaterialCurso::firstOrFail();
        self::assertFalse($material->publicado);
        self::assertSame($this->modulo->id, $material->modulo_id);
        $this->get($this->url($base.'/'.$material->id))->assertOk()->assertSee('Publicar material')->assertSee('rel="noopener noreferrer"', false);
        $this->put($this->url($base.'/'.$material->id), $this->datosMaterial(['titulo' => 'Recurso revisado', 'tipo' => 'documento']))->assertRedirect();
        $this->post($this->url($base.'/'.$material->id.'/publicar'))->assertRedirect();
        self::assertTrue($material->fresh()->publicado);
        $this->get($this->url($base.'/'.$material->id))->assertOk()->assertSee('Ocultar material')->assertDontSee('Publicar material');
        $this->put($this->url($base.'/'.$material->id), $this->datosMaterial())->assertSessionHasErrors('contenido');
        self::assertSame('Recurso revisado', $material->fresh()->titulo);
        $this->post($this->url($base.'/'.$material->id.'/ocultar'))->assertRedirect();
        self::assertFalse($material->fresh()->publicado);
        $this->assertDatabaseCount('bitacora', 4);
    }

    public function test_rechaza_estados_relaciones_y_campos_protegidos_en_formularios(): void
    {
        $this->post($this->url('/modulos'), $this->datosModulo(['curso_id' => 999]))->assertSessionHasErrors('curso_id');
        foreach (['publicado' => true, 'modulo_id' => 999, 'ruta_archivo' => 'privado/documento.pdf'] as $campo => $valor) {
            $this->post($this->url('/modulos/'.$this->modulo->id.'/materiales'), $this->datosMaterial([$campo => $valor]))->assertSessionHasErrors($campo);
        }
        $this->assertDatabaseCount('materiales_curso', 0);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_validaciones_de_modulo_y_material_no_dejan_escrituras(): void
    {
        foreach ([['titulo' => ''], ['orden' => 0], ['orden' => 65536], ['orden' => 1.5], ['obligatorio' => 'quizas']] as $cambio) {
            $this->post($this->url('/modulos'), $this->datosModulo($cambio))->assertSessionHasErrors(array_key_first($cambio));
        }
        foreach ([['tipo' => 'html'], ['titulo' => str_repeat('x', 181)], ['orden' => -1], ['url_externa' => ['invalida']]] as $cambio) {
            $this->post($this->url('/modulos/'.$this->modulo->id.'/materiales'), $this->datosMaterial($cambio))->assertSessionHasErrors(array_key_first($cambio));
        }
        $this->assertDatabaseCount('modulos_curso', 1);
        $this->assertDatabaseCount('materiales_curso', 0);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_orden_unico_incluye_registros_eliminados_logicamente_y_permite_posiciones_libres(): void
    {
        $material = $this->material();
        $this->post($this->url('/modulos'), $this->datosModulo(['orden' => 10]))->assertSessionHasErrors('orden');
        $base = '/modulos/'.$this->modulo->id.'/materiales';
        $this->post($this->url($base), $this->datosMaterial())->assertSessionHasErrors('orden');
        $material->delete();
        $this->post($this->url($base), $this->datosMaterial())->assertSessionHasErrors('orden');
        $this->post($this->url($base), $this->datosMaterial(['orden' => 5]))->assertRedirect();
        $this->get($this->url('/modulos/'.$this->modulo->id))->assertOk()->assertSee('Recurso educativo');
        $this->modulo->delete();
        $this->post($this->url('/modulos'), $this->datosModulo(['orden' => 10]))->assertSessionHasErrors('orden');
    }

    public function test_rechaza_curso_ajeno_y_modulo_o_material_que_no_corresponden_al_padre(): void
    {
        $ajeno = $this->group($this->otraInstitucion)->edicion->curso;
        $moduloAjeno = $this->record(ModuloCurso::class, ['curso_id' => $ajeno->id, 'titulo' => 'Privado ajeno', 'orden' => 1]);
        $materialAjeno = $this->record(MaterialCurso::class, ['modulo_id' => $moduloAjeno->id] + $this->datosMaterial());
        $raiz = '/gestion/'.$this->institucion->id.'/cursos/'.$ajeno->id.'/contenido';
        $this->get($raiz)->assertNotFound()->assertDontSee('Privado ajeno');
        $this->post($raiz.'/modulos', $this->datosModulo())->assertNotFound();
        $this->get($this->url('/modulos/'.$moduloAjeno->id))->assertNotFound();
        $this->put($this->url('/modulos/'.$moduloAjeno->id), $this->datosModulo())->assertNotFound();
        $base = '/modulos/'.$this->modulo->id.'/materiales/'.$materialAjeno->id;
        $this->get($this->url($base))->assertNotFound();
        $this->put($this->url($base), $this->datosMaterial())->assertNotFound();
        $this->post($this->url($base.'/publicar'))->assertNotFound();
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_rechaza_usuarios_sin_permiso_o_sin_rol_de_gestion_y_permisos_de_otra_institucion(): void
    {
        $sinPermiso = $this->userWithRole('coordinador');
        $soloParticipante = $this->participante->usuario;
        $this->grant($soloParticipante, 'cursos.gestionar', role: 'participante');
        $otro = $this->userWithRole('administrador', $this->otraInstitucion);
        $this->assign($otro, 'participante');
        foreach ([$sinPermiso, $soloParticipante, $otro] as $usuario) {
            $this->actingAs($usuario)->get($this->url())->assertForbidden();
            $this->post($this->url('/modulos'), $this->datosModulo())->assertForbidden();
        }
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_coordinador_con_permiso_y_ambito_correctos_puede_gestionar(): void
    {
        $coordinador = $this->userWithRole('coordinador');
        $this->grant($coordinador, 'cursos.gestionar', role: 'coordinador');
        $this->actingAs($coordinador)->post($this->url('/modulos'), $this->datosModulo())->assertRedirect();
        $this->get('/gestion/'.$this->institucion->id.'/contenido')->assertOk()->assertSee('Administrar contenido');
    }

    public function test_cuenta_inactiva_e_institucion_inactiva_no_pueden_gestionar(): void
    {
        $this->admin->forceFill(['estado_cuenta' => 'inactiva'])->save();
        $this->get($this->url())->assertForbidden();
        $this->post($this->url('/modulos'), $this->datosModulo())->assertForbidden();
        $this->admin->forceFill(['estado_cuenta' => 'activa'])->save();
        $this->institucion->forceFill(['estado' => 'inactiva'])->save();
        $this->get($this->url())->assertForbidden();
    }

    public function test_enlaces_peligrosos_y_tipos_desconocidos_no_pueden_publicarse_ni_guardarse(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,algo', 'file:///C:/privado', 'http://example.org/', 'https://localhost/x', 'https://127.0.0.1/x', 'https://usuario:clave@example.org/'] as $url) {
            $this->post($this->url('/modulos/'.$this->modulo->id.'/materiales'), $this->datosMaterial(['url_externa' => $url]))->assertSessionHasErrors('url_externa');
        }
        $material = $this->material(['url_externa' => 'javascript:alert(1)']);
        $this->post($this->url('/modulos/'.$this->modulo->id.'/materiales/'.$material->id.'/publicar'))->assertSessionHasErrors('url_externa');
        self::assertFalse($material->fresh()->publicado);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_protege_contenido_academico_historico_incluso_con_actividad_eliminada(): void
    {
        $material = $this->material();
        $actividad = $this->activity(['modulo_id' => $this->modulo->id]);
        $this->submission(activity: $actividad);
        $actividad->delete();
        $this->get($this->url())->assertOk()->assertSee('política de versiones')->assertDontSee('Crear módulo');
        $this->put($this->url('/modulos/'.$this->modulo->id), $this->datosModulo())->assertSessionHasErrors('contenido');
        $this->put($this->url('/modulos/'.$this->modulo->id.'/materiales/'.$material->id), $this->datosMaterial())->assertSessionHasErrors('contenido');
        $this->post($this->url('/modulos/'.$this->modulo->id.'/materiales/'.$material->id.'/publicar'))->assertSessionHasErrors('contenido');
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_constancias_y_condiciones_de_modulos_vinculados_permanecen_protegidas(): void
    {
        $this->activity(['modulo_id' => $this->modulo->id]);
        $this->put($this->url('/modulos/'.$this->modulo->id), $this->datosModulo(['orden' => 30]))->assertSessionHasErrors('contenido');
        $this->put($this->url('/modulos/'.$this->modulo->id), $this->datosModulo(['orden' => 10, 'titulo' => 'Corrección informativa']))->assertRedirect();
        $this->certificate($this->enrollment());
        $this->post($this->url('/modulos'), $this->datosModulo())->assertSessionHasErrors('contenido');
    }

    public function test_archivos_historicos_no_se_editan_ni_publican_como_enlaces(): void
    {
        $material = $this->material(['ruta_archivo' => 'privado/material.pdf']);
        $base = '/modulos/'.$this->modulo->id.'/materiales/'.$material->id;
        $this->put($this->url($base), $this->datosMaterial())->assertSessionHasErrors('contenido');
        $this->post($this->url($base.'/publicar'))->assertSessionHasErrors('contenido');
        $this->get($this->url($base))->assertOk()->assertDontSee('privado/material.pdf');
    }

    public function test_busqueda_filtros_paginacion_y_estados_vacios(): void
    {
        $this->material(['titulo' => 'Borrador oculto']);
        $this->material(['orden' => 20, 'titulo' => 'Video publicado', 'tipo' => 'video', 'publicado' => true]);
        $this->get($this->url('/modulos/'.$this->modulo->id.'?publicado=1'))->assertOk()->assertSee('Video publicado')->assertDontSee('Borrador oculto');
        $this->get($this->url('/modulos/'.$this->modulo->id.'?q=inexistente'))->assertOk()->assertSee('No hay materiales que mostrar');
        for ($i = 21; $i <= 35; $i++) {
            $this->material(['orden' => $i, 'titulo' => 'Recurso '.$i]);
        }
        $this->get($this->url('/modulos/'.$this->modulo->id))->assertOk()->assertSee('page=2', false);
        $this->get('/gestion/'.$this->institucion->id.'/contenido?q=inexistente')->assertOk()->assertSee('No hay cursos que coincidan');
        $this->get($this->url('?q=inexistente'))->assertOk()->assertSee('No hay módulos que mostrar');
    }

    public function test_formulario_preserva_textos_escapa_html_y_muestra_errores_en_espanol(): void
    {
        $ruta = $this->url('/modulos/'.$this->modulo->id.'/materiales/crear');
        $respuesta = $this->followingRedirects()->from($ruta)->post($this->url('/modulos/'.$this->modulo->id.'/materiales'), $this->datosMaterial(['titulo' => '<script>alert(1)</script>', 'url_externa' => 'http://example.org/']));
        $respuesta->assertOk()->assertSee('Usa un enlace HTTPS externo válido')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->assertDatabaseCount('materiales_curso', 0);
    }

    public function test_auditoria_fallida_revierte_la_escritura(): void
    {
        DB::statement("CREATE TRIGGER rechazar_auditoria BEFORE INSERT ON bitacora BEGIN SELECT RAISE(ABORT, 'auditoria indisponible'); END");
        $this->withoutExceptionHandling();
        $this->rejects(fn () => app(ContenidoCursoService::class)->guardarModulo($this->admin, $this->institucion->id, $this->grupo->edicion->curso_id, null, $this->datosModulo()), QueryException::class);
        $this->assertDatabaseCount('modulos_curso', 1);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_csrf_protege_creacion_edicion_publicacion_y_ocultamiento(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $material = $this->material();
        $base = '/modulos/'.$this->modulo->id.'/materiales';
        $this->post($this->url('/modulos'), $this->datosModulo())->assertStatus(419);
        $this->put($this->url('/modulos/'.$this->modulo->id), $this->datosModulo())->assertStatus(419);
        $this->post($this->url($base), $this->datosMaterial(['orden' => 20]))->assertStatus(419);
        $this->put($this->url($base.'/'.$material->id), $this->datosMaterial())->assertStatus(419);
        $this->post($this->url($base.'/'.$material->id.'/publicar'))->assertStatus(419);
        $this->post($this->url($base.'/'.$material->id.'/ocultar'))->assertStatus(419);
        $this->withSession(['_token' => 'token-local-prueba'])->post($this->url($base.'/'.$material->id.'/publicar'), ['_token' => 'token-local-prueba'])->assertRedirect();
        self::assertTrue($material->fresh()->publicado);
    }
}
