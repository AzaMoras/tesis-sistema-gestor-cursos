<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\CategoriaCurso;
use App\Models\Curso;
use App\Models\User;
use Tests\Support\ServiceTestCase;

class GestionOfertaEdicionTest extends ServiceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->grant($this->admin, 'cursos.gestionar');
        $this->actingAs($this->admin);
    }

    private function cursoUrl(?Curso $curso = null, string $sufijo = ''): string
    {
        return '/gestion/'.$this->institucion->id.'/cursos/'.($curso ?? $this->grupo->edicion->curso)->id.$sufijo;
    }

    public function test_curso_borrador_se_edita_y_publica_con_su_categoria(): void
    {
        $curso = $this->record(Curso::class, ['institucion_id' => $this->institucion->id, 'categoria_id' => $this->grupo->edicion->curso->categoria_id, 'titulo' => 'Borrador', 'descripcion' => 'Inicial']);
        $this->put($this->cursoUrl($curso), ['categoria_id' => $curso->categoria_id, 'titulo' => 'Borrador corregido', 'descripcion' => 'Descripción completa', 'objetivos' => 'Objetivos revisados'])->assertSessionHasNoErrors()->assertRedirect();
        self::assertSame('borrador', $curso->fresh()->estado);
        $this->post($this->cursoUrl($curso, '/publicar'))->assertSessionHasNoErrors()->assertRedirect();
        self::assertSame('publicado', $curso->fresh()->estado);
    }

    public function test_publicado_admite_informacion_general_con_auditoria_y_sin_cambiar_relaciones(): void
    {
        $curso = $this->grupo->edicion->curso;
        $this->put($this->cursoUrl(), ['titulo' => 'Hardware: introducción', 'descripcion' => 'Descripción corregida', 'objetivos' => 'Objetivos aclarados'])->assertSessionHasNoErrors()->assertRedirect();
        $actual = $curso->fresh();
        self::assertSame('publicado', $actual->estado);
        self::assertSame('Hardware: introducción', $actual->titulo);
        self::assertSame('Descripción corregida', $actual->descripcion);
        self::assertSame('Objetivos aclarados', $actual->objetivos);
        self::assertSame($curso->categoria_id, $actual->categoria_id);
        self::assertSame($curso->institucion_id, $actual->institucion_id);
        $auditoria = Bitacora::sole();
        self::assertSame('cursos.editar', $auditoria->accion);
        self::assertSame('informacion_general', $auditoria->detalles_json['alcance']);
        self::assertSame($curso->titulo, $auditoria->detalles_json['anterior']['titulo']);
        self::assertSame('Hardware: introducción', $auditoria->detalles_json['nuevo']['titulo']);
    }

    public function test_publicado_rechaza_campos_estructurales_y_estado_sin_escritura_parcial(): void
    {
        $curso = $this->grupo->edicion->curso;
        $categoria = $this->record(CategoriaCurso::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'Otra', 'slug' => 'otra']);
        $antes = $curso->fresh()->getAttributes();
        foreach (['categoria_id' => $categoria->id, 'institucion_id' => $this->otraInstitucion->id, 'estado' => 'borrador', 'requisitos' => [1], 'precio' => '100', 'calificacion_minima' => 10, 'ediciones' => [], 'modulos' => []] as $campo => $valor) {
            $this->put($this->cursoUrl(), ['titulo' => 'Cambio parcial', 'descripcion' => 'Cambio parcial', $campo => $valor])->assertSessionHasErrors($campo);
            self::assertSame($antes, $curso->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_con_inscripciones_solo_corrige_titulo_y_conserva_condiciones_y_progreso(): void
    {
        $curso = $this->grupo->edicion->curso;
        $inscripcion = $this->enrollment();
        $entrega = $this->submission($inscripcion);
        $historial = $entrega->fresh()->getAttributes();
        $condiciones = $inscripcion->fresh()->getAttributes();
        $this->put($this->cursoUrl(), ['titulo' => 'Título corregido'])->assertSessionHasNoErrors()->assertRedirect();
        foreach (['descripcion', 'objetivos'] as $campo) {
            $this->put($this->cursoUrl(), ['titulo' => 'Otro título', $campo => 'Contenido sustantivo nuevo'])->assertSessionHasErrors($campo);
        }
        self::assertSame('Título corregido', $curso->fresh()->titulo);
        self::assertSame($curso->descripcion, $curso->fresh()->descripcion);
        self::assertSame($curso->objetivos, $curso->fresh()->objetivos);
        self::assertSame('publicado', $curso->fresh()->estado);
        self::assertSame($historial, $entrega->fresh()->getAttributes());
        self::assertSame($condiciones, $inscripcion->fresh()->getAttributes());
        $this->get($this->cursoUrl(null, '/editar'))->assertOk()->assertSee('solo puedes corregir el título')->assertSee('readonly', false);
        $this->assertDatabaseCount('bitacora', 1);
    }

    public function test_inscripciones_historicas_de_grupos_eliminados_siguen_protegiendo_el_curso(): void
    {
        $curso = $this->grupo->edicion->curso;
        $this->enrollment(['estado' => 'cancelada']);
        $this->grupo->delete();
        $this->grupo->edicion->delete();
        $this->put($this->cursoUrl($curso), ['titulo' => 'Título', 'descripcion' => 'Cambio de condiciones'])->assertSessionHasErrors('descripcion');
        self::assertSame($curso->descripcion, $curso->fresh()->descripcion);
    }

    public function test_se_validan_campos_generales_y_no_se_inyecta_html_en_tarjetas(): void
    {
        foreach ([['titulo' => '', 'descripcion' => 'Texto'], ['titulo' => str_repeat('x', 181), 'descripcion' => 'Texto'], ['titulo' => 'Título', 'descripcion' => []], ['titulo' => 'Título', 'descripcion' => 'Texto', 'objetivos' => ['valor']]] as $datos) {
            $this->put($this->cursoUrl(), $datos)->assertSessionHasErrors();
        }
        $this->put($this->cursoUrl(), ['titulo' => '<script>alert(1)</script>', 'descripcion' => 'Texto'])->assertSessionHasNoErrors();
        $this->get('/gestion/'.$this->institucion->id.'/cursos')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_url_ajena_y_usuarios_sin_rol_o_permiso_no_editan(): void
    {
        $otro = $this->group($this->otraInstitucion)->edicion->curso;
        $this->put($this->cursoUrl($otro), ['titulo' => 'Ajeno', 'descripcion' => 'Texto'])->assertNotFound();
        $this->get($this->cursoUrl($otro))->assertNotFound();
        foreach ([$this->userWithRole('coordinador'), $this->participante->usuario, User::factory()->create(['estado_cuenta' => 'activa'])] as $usuario) {
            $this->actingAs($usuario)->put($this->cursoUrl(), ['titulo' => 'Intrusión', 'descripcion' => 'Texto'])->assertForbidden();
            $this->get($this->cursoUrl(null, '/editar'))->assertForbidden();
            $this->get($this->cursoUrl())->assertForbidden();
        }
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_tarjetas_distinguen_detalles_edicion_y_publicacion(): void
    {
        $curso = $this->grupo->edicion->curso;
        $listado = '/gestion/'.$this->institucion->id.'/cursos';
        $this->get($listado)->assertOk()->assertSee('Crear curso')->assertSee('Ver detalles')->assertSee('Editar')
            ->assertSee($this->cursoUrl(), false)->assertSee($this->cursoUrl(null, '/editar'), false)->assertDontSee('Publicar curso');
        $curso->forceFill(['estado' => 'archivado'])->save();
        $this->get($listado)->assertOk()->assertSee('Ver detalles')->assertDontSee('aria-label="Editar ', false)->assertDontSee('Publicar curso');
        $this->get($this->cursoUrl())->assertOk()->assertViewHas('soloLectura', true)->assertDontSee('Guardar cambios');
        $curso->forceFill(['estado' => 'borrador'])->save();
        $this->grupo->edicion->forceFill(['estado' => 'borrador'])->save();
        $this->grupo->forceFill(['estado' => 'borrador'])->save();
        $this->get($listado)->assertOk()->assertSee('Editar')->assertSee('Publicar curso');
        foreach (['categorias' => ['Crear categoría', $curso->categoria_id], 'ediciones' => ['Crear edición', $this->grupo->edicion_id], 'grupos' => ['Crear grupo', $this->grupo->id]] as $tipo => [$crear, $id]) {
            $this->get('/gestion/'.$this->institucion->id.'/'.$tipo)->assertOk()->assertSee($crear)->assertSee('Ver detalles')->assertSee('Editar');
            $this->get('/gestion/'.$this->institucion->id.'/'.$tipo.'/'.$id)->assertOk()->assertViewHas('editable', false)->assertDontSee('Guardar cambios');
        }
    }

    public function test_panel_en_mi_cuenta_exige_rol_permiso_y_ambito_activo_sin_id_fijo(): void
    {
        $this->get('/mi-cuenta')->assertOk()->assertSee('Panel de gestión')->assertSee('href="'.route('gestion.instituciones').'"', false);
        $this->get('/gestion')->assertOk()->assertSee($this->institucion->nombre);
        $usuario = $this->userWithRole('coordinador');
        $this->grant($usuario, 'cursos.gestionar', $this->otraInstitucion, 'participante');
        $this->actingAs($usuario)->get('/mi-cuenta')->assertOk()->assertDontSee('Panel de gestión');
        $this->grant($usuario, 'cursos.gestionar', role: 'coordinador');
        $this->get('/mi-cuenta')->assertOk()->assertSee('Panel de gestión');
        $this->institucion->forceFill(['estado' => 'inactiva'])->save();
        $this->get('/mi-cuenta')->assertOk()->assertDontSee('Panel de gestión');
        $sinRol = User::factory()->create(['estado_cuenta' => 'activa']);
        $this->actingAs($sinRol)->get('/mi-cuenta')->assertOk()->assertDontSee('Panel de gestión');
    }

    public function test_detalles_no_ofrece_guardar_y_slug_solo_se_sugiere_al_crear(): void
    {
        $this->get($this->cursoUrl())->assertOk()->assertViewHas('soloLectura', true)->assertViewHas('editable', false)->assertSee('<dl class="gestion-detalles">', false)->assertDontSee('Guardar cambios');
        $this->get('/gestion/'.$this->institucion->id.'/categorias/crear')->assertOk()->assertSee('data-slug-sugerido', false)->assertSee('gestion.js');
        $id = $this->grupo->edicion->curso->categoria_id;
        $this->get('/gestion/'.$this->institucion->id.'/categorias/'.$id.'/editar')->assertOk()->assertDontSee('data-slug-sugerido', false);
    }

    public function test_id_cero_en_edicion_no_se_interpreta_como_creacion(): void
    {
        $url = '/gestion/'.$this->institucion->id.'/cursos/0';
        $this->get($url)->assertNotFound();
        $this->get($url.'/editar')->assertNotFound();
        $this->put($url, ['categoria_id' => $this->grupo->edicion->curso->categoria_id, 'titulo' => 'No crear', 'descripcion' => 'Texto'])->assertNotFound();
        $this->assertDatabaseCount('cursos', 1);
        $this->assertDatabaseCount('bitacora', 0);
    }
}
