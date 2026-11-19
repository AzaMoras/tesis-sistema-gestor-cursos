<?php

namespace Tests\Feature;

use App\Models\CategoriaCurso;
use App\Models\Curso;
use App\Models\Edicion;
use App\Models\Grupo;
use App\Models\User;
use App\Services\Soporte\ReglasOperacion;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Support\ServiceTestCase;

class GestionOfertaTest extends ServiceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->grant($this->admin, 'cursos.gestionar');
        $this->actingAs($this->admin);
    }

    private function url(string $tipo, string $sufijo = '', ?int $institucion = null): string
    {
        return '/gestion/'.($institucion ?? $this->institucion->id).'/'.$tipo.$sufijo;
    }

    private function datos(string $tipo): array
    {
        return match ($tipo) {
            'categorias' => ['nombre' => 'Tecnología', 'slug' => 'tecnologia', 'descripcion' => 'Formación virtual'],
            'cursos' => ['categoria_id' => $this->grupo->edicion->curso->categoria_id, 'titulo' => 'Curso nuevo', 'descripcion' => 'Descripción del curso'],
            'ediciones' => ['curso_id' => $this->grupo->edicion->curso_id, 'clave' => 'ED-01', 'modalidad' => 'autogestionado', 'fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-12-20', 'precio' => '0.00', 'moneda' => 'MXN'],
            'grupos' => ['edicion_id' => $this->grupo->edicion_id, 'codigo' => 'G-01', 'cupo_maximo' => 10],
        };
    }

    public function test_creacion_edicion_relaciones_y_pantallas_de_los_cuatro_recursos(): void
    {
        foreach (['categorias' => CategoriaCurso::class, 'cursos' => Curso::class, 'ediciones' => Edicion::class, 'grupos' => Grupo::class] as $tipo => $modelo) {
            $datos = $this->datos($tipo);
            $this->get($this->url($tipo.'/crear'))->assertOk()->assertSee($tipo === 'categorias' ? 'Guardar categoría' : 'Guardar borrador');
            $this->post($this->url($tipo), $datos + ['institucion_id' => $this->otraInstitucion->id, 'estado' => 'publicado', 'user_id' => 999])->assertRedirect($this->url($tipo));
            $registro = $modelo::latest('id')->firstOrFail();
            self::assertSame($tipo === 'categorias' ? 'activa' : 'borrador', $registro->estado);
            $campo = match ($tipo) {
                'categorias' => 'nombre', 'cursos' => 'titulo', 'ediciones' => 'clave', 'grupos' => 'codigo'
            };
            $datos[$campo] = 'Actualizado';
            $this->put($this->url($tipo, '/'.$registro->id), $datos)->assertRedirect($this->url($tipo));
            self::assertSame('Actualizado', $registro->fresh()->$campo);
            $this->get($this->url($tipo, '/'.$registro->id.'/editar'))->assertOk()->assertSee('Actualizado');
            $this->get($this->url($tipo))->assertOk()->assertSee('Actualizado');
            if (in_array($tipo, ['categorias', 'cursos'])) {
                self::assertSame($this->institucion->id, $registro->institucion_id);
            }
            if ($tipo === 'cursos') {
                self::assertSame($datos['categoria_id'], $registro->categoria_id);
            }
            if ($tipo === 'ediciones') {
                self::assertSame($datos['curso_id'], $registro->curso_id);
                self::assertSame('0.00', $registro->precio);
            }
            if ($tipo === 'grupos') {
                self::assertSame($datos['edicion_id'], $registro->edicion_id);
                self::assertSame(10, $registro->cupo_maximo);
            }
        }
        $this->assertDatabaseCount('bitacora', 8);
    }

    public function test_publicacion_secuencial_y_proteccion_posterior(): void
    {
        $curso = $this->grupo->edicion->curso;
        $edicion = $this->grupo->edicion;
        foreach ([$curso, $edicion, $this->grupo] as $registro) {
            $registro->forceFill(['estado' => 'borrador'])->save();
        }
        $this->post($this->url('ediciones', '/'.$edicion->id.'/publicar'))->assertSessionHasErrors('estado');
        $this->post($this->url('grupos', '/'.$this->grupo->id.'/publicar'))->assertSessionHasErrors('estado');
        foreach (['cursos' => $curso, 'ediciones' => $edicion, 'grupos' => $this->grupo] as $tipo => $registro) {
            $this->post($this->url($tipo, '/'.$registro->id.'/publicar'))->assertSessionHasNoErrors()->assertRedirect();
            self::assertSame(config('operacion.publicacion.'.$tipo), $registro->fresh()->estado);
            $this->post($this->url($tipo, '/'.$registro->id.'/publicar'))->assertSessionHasErrors('estado');
            $datosEdicion = $this->datos($tipo);
            if ($tipo === 'cursos') {
                // La información general ahora es editable; el estado sigue protegido.
                $datosEdicion['estado'] = 'borrador';
            }
            $this->put($this->url($tipo, '/'.$registro->id), $datosEdicion)->assertSessionHasErrors('estado');
        }
        $this->assertDatabaseCount('bitacora', 3);
    }

    public function test_validaciones_no_dejan_escrituras_parciales(): void
    {
        foreach (['categorias', 'cursos', 'ediciones', 'grupos'] as $tipo) {
            $this->post($this->url($tipo), [])->assertSessionHasErrors();
        }
        foreach ([null, 0, -1, '1.5', '4294967296'] as $cupo) {
            $this->post($this->url('grupos'), array_replace($this->datos('grupos'), ['cupo_maximo' => $cupo]))->assertSessionHasErrors('cupo_maximo');
        }
        foreach ([['modalidad' => 'presencial'], ['fecha_fin' => '2026-01-01'], ['precio' => '-1'], ['precio' => '1.001'], ['moneda' => 'XYZ'], ['cierre_inscripciones_at' => '2027-01-01T12:00']] as $cambio) {
            $this->post($this->url('ediciones'), array_replace($this->datos('ediciones'), $cambio))->assertSessionHasErrors(array_key_first($cambio));
        }
        $this->assertDatabaseCount('bitacora', 0);
        $this->assertDatabaseCount('grupos', 1);
    }

    public function test_unicidad_en_el_ambito_y_registros_eliminados(): void
    {
        foreach (['categorias' => CategoriaCurso::class, 'ediciones' => Edicion::class, 'grupos' => Grupo::class] as $tipo => $modelo) {
            $datos = $this->datos($tipo);
            $this->post($this->url($tipo), $datos)->assertRedirect();
            $this->post($this->url($tipo), $datos)->assertSessionHasErrors();
            $modelo::latest('id')->first()->delete();
            $this->post($this->url($tipo), $datos)->assertSessionHasErrors();
        }
    }

    public function test_aislamiento_de_referencias_y_urls(): void
    {
        $otro = $this->group($this->otraInstitucion);
        foreach (['categorias' => $otro->edicion->curso->categoria, 'cursos' => $otro->edicion->curso, 'ediciones' => $otro->edicion, 'grupos' => $otro] as $tipo => $registro) {
            $this->get($this->url($tipo, '/'.$registro->id.'/editar'))->assertNotFound();
            $this->put($this->url($tipo, '/'.$registro->id), $this->datos($tipo))->assertNotFound();
            if ($tipo !== 'categorias') {
                $this->post($this->url($tipo, '/'.$registro->id.'/publicar'))->assertNotFound();
            }
        }
        foreach (['cursos' => ['categoria_id' => $otro->edicion->curso->categoria_id], 'ediciones' => ['curso_id' => $otro->edicion->curso_id], 'grupos' => ['edicion_id' => $otro->edicion_id]] as $tipo => $cambio) {
            $this->post($this->url($tipo), array_replace($this->datos($tipo), $cambio))->assertSessionHasErrors(array_key_first($cambio));
        }
        $this->get($this->url('cursos', '', $this->otraInstitucion->id))->assertForbidden();
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_no_se_pueden_trasladar_ediciones_ni_grupos(): void
    {
        $otro = $this->group();
        $this->grupo->edicion->forceFill(['estado' => 'borrador'])->save();
        $this->grupo->forceFill(['estado' => 'borrador'])->save();
        $this->put($this->url('ediciones', '/'.$this->grupo->edicion_id), array_replace($this->datos('ediciones'), ['curso_id' => $otro->edicion->curso_id]))->assertSessionHasErrors('curso_id');
        $this->put($this->url('grupos', '/'.$this->grupo->id), array_replace($this->datos('grupos'), ['edicion_id' => $otro->edicion_id]))->assertSessionHasErrors('edicion_id');
        self::assertSame($this->grupo->edicion_id, $this->grupo->fresh()->edicion_id);
    }

    public function test_roles_permisos_cuenta_e_institucion_son_independientes(): void
    {
        $sinPermiso = $this->userWithRole('coordinador');
        $participante = $this->participante->usuario;
        // Incluso un participante con el permiso necesita un rol de gestión.
        $this->grant($participante, 'cursos.gestionar', role: 'participante');
        foreach ([$sinPermiso, $participante, User::factory()->create(['estado_cuenta' => 'activa'])] as $usuario) {
            $this->actingAs($usuario);
            $this->get($this->url('cursos'))->assertForbidden();
            $this->post($this->url('cursos'), $this->datos('cursos'))->assertForbidden();
            $this->put($this->url('cursos', '/'.$this->grupo->edicion->curso_id), $this->datos('cursos'))->assertForbidden();
            $this->post($this->url('cursos', '/'.$this->grupo->edicion->curso_id.'/publicar'))->assertForbidden();
        }
        $this->admin->forceFill(['estado_cuenta' => 'suspendida'])->save();
        $this->actingAs($this->admin)->get($this->url('cursos'))->assertForbidden();
        $this->admin->forceFill(['estado_cuenta' => 'activa'])->save();
        $this->institucion->forceFill(['estado' => 'inactiva'])->save();
        $this->actingAs($this->admin)->get($this->url('cursos'))->assertForbidden();
    }

    public function test_coordinador_con_permiso_puede_gestionar_y_filtrar(): void
    {
        $coordinador = $this->userWithRole('coordinador');
        $this->grant($coordinador, 'cursos.gestionar', role: 'coordinador');
        $this->actingAs($coordinador)->post($this->url('cursos'), $this->datos('cursos'))->assertRedirect();
        $this->get('/gestion')->assertOk()->assertSee($this->institucion->nombre);
        $this->get($this->url('cursos', '?q=Curso+nuevo&estado=borrador'))->assertOk()->assertSee('Curso nuevo');
        $this->get($this->url('cursos', '?q=inexistente'))->assertOk()->assertSee('No hay registros');
    }

    public function test_invitado_no_puede_operar_y_get_no_publica(): void
    {
        $this->get($this->url('cursos', '/'.$this->grupo->edicion->curso_id.'/publicar'))->assertMethodNotAllowed();
        auth()->forgetGuards();
        $this->get($this->url('cursos'))->assertRedirect('/login');
        $this->post($this->url('cursos'), $this->datos('cursos'))->assertRedirect('/login');
    }

    public function test_no_publica_cupo_nulo_ni_estados_desconocidos(): void
    {
        $this->grupo->forceFill(['estado' => 'borrador', 'cupo_maximo' => null])->save();
        $this->post($this->url('grupos', '/'.$this->grupo->id.'/publicar'))->assertSessionHasErrors('cupo_maximo');
        $curso = $this->grupo->edicion->curso;
        $curso->forceFill(['estado' => 'archivado'])->save();
        $this->post($this->url('cursos', '/'.$curso->id.'/publicar'))->assertSessionHasErrors('estado');
        self::assertNull($this->grupo->fresh()->cupo_maximo);
    }

    public function test_categoria_inactiva_o_ajena_inconsistente_no_habilita_curso(): void
    {
        $curso = $this->grupo->edicion->curso;
        $categoria = $curso->categoria;
        $curso->forceFill(['estado' => 'borrador'])->save();
        $categoria->forceFill(['estado' => 'inactiva'])->save();
        $this->post($this->url('cursos', '/'.$curso->id.'/publicar'))->assertSessionHasErrors('categoria_id');
        $categoria->forceFill(['institucion_id' => $this->otraInstitucion->id])->save();
        $this->get($this->url('cursos', '/'.$curso->id.'/editar'))->assertNotFound();
    }

    public function test_configuracion_operativa_explicita_sin_inventar_reservas(): void
    {
        $this->app->forgetInstance(ReglasOperacion::class);
        $reglas = $this->app->make(ReglasOperacion::class);
        self::assertSame(['abierta'], $reglas->estadosDeApertura('grupo'));
        self::assertSame('confirmada', $reglas->estado('inscripcion.confirmada'));
        self::assertSame('cancelada', $reglas->estado('inscripcion.cancelada'));
        self::assertFalse($reglas->cupoNuloEsIlimitado());
        $this->expectException(\LogicException::class);
        $reglas->minutosDeReserva();
    }

    public function test_demo_rechaza_base_de_testing_sin_escribir(): void
    {
        $this->artisan('aula-verde:demo-oferta', ['correo' => 'demo@example.test'])->assertFailed();
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_datos_manipulados_y_referencias_eliminadas_se_rechazan(): void
    {
        foreach ([['curso_id' => ['id' => 1]], ['clave' => ['valor']], ['precio' => ['valor']], ['fecha_inicio' => '2026-02-30']] as $cambio) {
            $this->post($this->url('ediciones'), array_replace($this->datos('ediciones'), $cambio))->assertSessionHasErrors(array_key_first($cambio));
        }
        $this->post($this->url('grupos'), array_replace($this->datos('grupos'), ['edicion_id' => [1]]))->assertSessionHasErrors('edicion_id');
        $otro = $this->group();
        $otro->edicion->delete();
        $this->post($this->url('grupos'), array_replace($this->datos('grupos'), ['edicion_id' => $otro->edicion_id]))->assertSessionHasErrors('edicion_id');
        $otro->edicion->curso->categoria->delete();
        $this->post($this->url('cursos'), array_replace($this->datos('cursos'), ['categoria_id' => $otro->edicion->curso->categoria_id]))->assertSessionHasErrors('categoria_id');
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_permisos_de_una_institucion_no_se_extienden_a_otra(): void
    {
        $this->assign($this->admin, 'coordinador', $this->otraInstitucion);
        foreach (['categorias', 'cursos', 'ediciones', 'grupos'] as $tipo) {
            $this->get($this->url($tipo, '', $this->otraInstitucion->id))->assertForbidden();
            $this->post($this->url($tipo, '', $this->otraInstitucion->id), $this->datos($tipo))->assertForbidden();
        }
        $this->get('/gestion')->assertOk()->assertSee($this->institucion->nombre);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_paginacion_y_busqueda_conservan_el_ambito(): void
    {
        for ($i = 1; $i <= 16; $i++) {
            $this->record(CategoriaCurso::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'Visible '.$i, 'slug' => 'visible-'.$i]);
        }
        $this->record(CategoriaCurso::class, ['institucion_id' => $this->otraInstitucion->id, 'nombre' => 'Información ajena', 'slug' => 'ajena']);
        $this->get($this->url('categorias', '?q=Visible'))->assertOk()->assertDontSee('Información ajena')->assertViewHas('registros', fn ($lista) => $lista->total() === 16 && $lista->count() === 15);
        $this->get($this->url('categorias', '?q=Visible&page=2'))->assertOk()->assertViewHas('registros', fn ($lista) => $lista->count() === 1);
    }

    public function test_borradores_con_descendientes_operativos_no_son_editables(): void
    {
        $curso = $this->grupo->edicion->curso;
        $curso->forceFill(['estado' => 'borrador'])->save();
        $this->put($this->url('cursos', '/'.$curso->id), $this->datos('cursos'))->assertSessionHasErrors('estado');
        $this->enrollment();
        $this->grupo->forceFill(['estado' => 'borrador'])->save();
        $this->put($this->url('grupos', '/'.$this->grupo->id), $this->datos('grupos'))->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_csrf_protege_creacion_edicion_y_publicacion(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post($this->url('cursos'), $this->datos('cursos'))->assertStatus(419);
        $this->put($this->url('cursos', '/'.$this->grupo->edicion->curso_id), $this->datos('cursos'))->assertStatus(419);
        $this->post($this->url('cursos', '/'.$this->grupo->edicion->curso_id.'/publicar'))->assertStatus(419);
        $this->withSession(['_token' => 'token-de-prueba'])->post($this->url('cursos'), $this->datos('cursos') + ['_token' => 'token-de-prueba'])->assertRedirect();
        $this->assertDatabaseCount('bitacora', 1);
    }
}
