<?php

namespace Tests\Feature;

use App\Models\CategoriaCurso;
use App\Models\Curso;
use App\Models\Edicion;
use App\Models\Grupo;
use App\Models\Institucion;
use App\Models\Requisito;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogoCursosTest extends TestCase
{
    use RefreshDatabase;

    private Institucion $institucion;

    private CategoriaCurso $categoria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(12, 0));
        $this->institucion = $this->record(Institucion::class, ['nombre' => 'Institución de prueba', 'slug' => 'prueba', 'estado' => 'activa']);
        $this->categoria = $this->record(CategoriaCurso::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'Tecnología', 'slug' => 'tecnologia', 'estado' => 'activa']);
    }

    private function record(string $class, array $attributes): Model
    {
        $record = new $class;
        $record->forceFill($attributes)->save();

        return $record;
    }

    private function curso(array $attributes = []): Curso
    {
        return $this->record(Curso::class, $attributes + ['institucion_id' => $this->institucion->id, 'categoria_id' => $this->categoria->id,
            'titulo' => 'Hardware introductorio', 'descripcion' => 'Descripción pública del programa.', 'objetivos' => 'Reconocer componentes.', 'estado' => 'publicado']);
    }

    private function edicion(Curso $curso, array $attributes = []): Edicion
    {
        return $this->record(Edicion::class, $attributes + ['curso_id' => $curso->id, 'clave' => 'ED-'.uniqid(), 'modalidad' => 'acompanada',
            'fecha_inicio' => '2026-11-23', 'fecha_fin' => '2026-12-23', 'cierre_inscripciones_at' => '2026-11-22 18:00:00', 'precio' => '150.50', 'moneda' => 'MXN', 'estado' => 'abierta']);
    }

    private function grupo(Edicion $edicion, array $attributes = []): Grupo
    {
        return $this->record(Grupo::class, $attributes + ['edicion_id' => $edicion->id, 'codigo' => 'GR-'.uniqid(), 'cupo_maximo' => 20, 'estado' => 'abierta']);
    }

    public function test_catalogo_y_detalle_son_publicos_y_muestran_datos_reales(): void
    {
        $curso = $this->curso();
        $this->grupo($this->edicion($curso));
        $this->get('/cursos')->assertOk()->assertViewIs('publico.cursos.index')->assertSee($curso->titulo)
            ->assertSee('Tecnología')->assertSee($this->institucion->nombre)->assertSee('Con acompañamiento')
            ->assertSee('150.50 MXN')->assertSee('Edición abierta · plazas por confirmar')->assertSee('Ver detalles');
        $this->get('/cursos/'.$curso->id)->assertOk()->assertSee($curso->descripcion)->assertSee($curso->objetivos)
            ->assertSee('23/11/2026')->assertSee('23/12/2026')->assertSee('150.50 MXN')->assertSee('Capacidad máxima registrada: 20.')
            ->assertSee('Inscribirme · Próximamente')->assertSee('disabled', false);
        $this->assertGuest();
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertDatabaseCount('usuario_rol', 0);
    }

    public function test_borradores_y_estados_desconocidos_no_son_publicos_incluso_con_sesion(): void
    {
        foreach (['borrador', 'archivado', 'publicada'] as $estado) {
            $curso = $this->curso(['estado' => $estado, 'titulo' => 'Privado '.$estado]);
            $this->get('/cursos')->assertDontSee($curso->titulo);
            $this->get('/')->assertDontSee($curso->titulo);
            $this->get('/cursos/'.$curso->id)->assertNotFound();
            $this->actingAs(User::factory()->create(['estado_cuenta' => 'activa']))
                ->get('/cursos/'.$curso->id)->assertNotFound();
        }
    }

    public function test_curso_eliminado_logicamente_no_es_publico(): void
    {
        $curso = $this->curso();
        $curso->delete();
        $this->get('/cursos')->assertDontSee($curso->titulo);
        $this->get('/cursos/'.$curso->id)->assertNotFound();
        $this->get('/')->assertDontSee($curso->titulo);
    }

    public function test_institucion_inactiva_o_eliminada_oculta_sus_cursos(): void
    {
        $curso = $this->curso();
        $this->institucion->forceFill(['estado' => 'inactiva'])->save();
        $this->get('/cursos')->assertDontSee($curso->titulo)->assertDontSee('Tecnología');
        $this->get('/cursos/'.$curso->id)->assertNotFound();
        $this->institucion->forceFill(['estado' => 'activa'])->save();
        $this->institucion->delete();
        $this->get('/cursos/'.$curso->id)->assertNotFound();
        $this->get('/')->assertDontSee($curso->titulo);
    }

    public function test_categoria_inactiva_o_eliminada_oculta_sus_cursos(): void
    {
        $curso = $this->curso();
        $this->categoria->forceFill(['estado' => 'inactiva'])->save();
        $this->get('/cursos')->assertDontSee($curso->titulo);
        $this->get('/cursos/'.$curso->id)->assertNotFound();
        $this->categoria->forceFill(['estado' => 'activa'])->save();
        $this->categoria->delete();
        $this->get('/cursos/'.$curso->id)->assertNotFound();
    }

    public function test_categoria_de_otra_institucion_no_expone_curso_inconsistente(): void
    {
        $otra = $this->record(Institucion::class, ['nombre' => 'Otra institución', 'slug' => 'otra']);
        $curso = $this->curso(['institucion_id' => $otra->id]);
        $this->get('/cursos')->assertDontSee($curso->titulo);
        $this->get('/cursos/'.$curso->id)->assertNotFound();
    }

    public function test_solo_ediciones_y_grupos_abiertos_y_no_eliminados_son_visibles(): void
    {
        $curso = $this->curso();
        $abierta = $this->edicion($curso, ['clave' => 'ED-PUBLICA']);
        $this->grupo($abierta, ['codigo' => 'GR-PUBLICO']);
        foreach (['borrador', 'cerrada', 'abierto'] as $estado) {
            $privada = $this->edicion($curso, ['clave' => 'ED-PRIVADA-'.$estado, 'estado' => $estado]);
            $this->grupo($privada, ['codigo' => 'GR-ED-PRIVADA-'.$estado]);
            $this->grupo($abierta, ['codigo' => 'GR-PRIVADO-'.$estado, 'estado' => $estado]);
        }
        $baja = $this->edicion($curso, ['clave' => 'ED-ELIMINADA']);
        $this->grupo($baja, ['codigo' => 'GR-ED-ELIMINADA']);
        $baja->delete();
        $this->grupo($abierta, ['codigo' => 'GR-ELIMINADO'])->delete();
        $this->get('/cursos/'.$curso->id)->assertOk()->assertSee('ED-PUBLICA')->assertSee('GR-PUBLICO')
            ->assertDontSee('ED-PRIVADA')->assertDontSee('GR-PRIVADO')->assertDontSee('GR-ED-PRIVADA')
            ->assertDontSee('ED-ELIMINADA')->assertDontSee('GR-ELIMINADO');
    }

    public function test_publicado_sin_ediciones_sigue_visible_sin_promesas_de_oferta(): void
    {
        $curso = $this->curso();
        $this->get('/cursos')->assertSee($curso->titulo)->assertSee('Sin oferta de inscripción vigente')->assertDontSee('Gratuito');
        $this->get('/cursos/'.$curso->id)->assertOk()->assertSee('todavía no tiene ediciones abiertas');
    }

    public function test_cierre_vencido_fin_pasado_y_fechas_invalidas_no_generan_oferta_vigente(): void
    {
        $curso = $this->curso();
        foreach ([['cierre_inscripciones_at' => now()], ['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-11-18'],
            ['fecha_inicio' => '2026-12-25', 'fecha_fin' => '2026-12-23']] as $datos) {
            $this->grupo($this->edicion($curso, $datos));
        }
        $this->get('/cursos')->assertSee('Sin oferta de inscripción vigente')->assertDontSee('150.50 MXN');
        $this->get('/cursos/'.$curso->id)->assertSee('Fuera de plazo o fechas por confirmar')->assertSee('Precio registrado');
    }

    public function test_cupo_nulo_cero_o_grupo_cerrado_no_anuncian_oferta_vigente(): void
    {
        $curso = $this->curso();
        $edicion = $this->edicion($curso);
        $this->grupo($edicion, ['cupo_maximo' => null]);
        $this->grupo($edicion, ['cupo_maximo' => 0]);
        $this->grupo($edicion, ['estado' => 'cerrada']);
        $this->get('/cursos')->assertSee('Sin oferta de inscripción vigente')->assertDontSee('150.50 MXN');
        $this->get('/cursos/'.$curso->id)->assertSee('Capacidad pendiente de confirmar.')->assertDontSee('plazas disponibles:');
    }

    public function test_gratuidad_y_monedas_corresponden_a_cada_edicion(): void
    {
        $curso = $this->curso();
        $this->grupo($this->edicion($curso, ['precio' => '0.00', 'modalidad' => 'autogestionado']));
        $this->grupo($this->edicion($curso, ['precio' => '25.00', 'moneda' => 'USD']));
        $this->get('/cursos')->assertSee('Gratuito · MXN')->assertSee('25.00 USD')->assertSee('Autogestionado');
        $this->get('/cursos/'.$curso->id)->assertSee('Gratuito · MXN')->assertSee('25.00 USD');
    }

    public function test_requisitos_son_reales_activos_institucionales_y_sin_evidencias_privadas(): void
    {
        $curso = $this->curso();
        $requisito = $this->record(Requisito::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'Conocimientos básicos', 'descripcion' => 'Comprender conceptos iniciales.', 'requiere_documento' => true]);
        DB::table('curso_requisito')->insert(['curso_id' => $curso->id, 'requisito_id' => $requisito->id, 'obligatorio' => true]);
        $opcional = $this->record(Requisito::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'Experiencia previa']);
        DB::table('curso_requisito')->insert(['curso_id' => $curso->id, 'requisito_id' => $opcional->id, 'obligatorio' => false]);
        $inactivo = $this->record(Requisito::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'REQUISITO-INACTIVO', 'estado' => 'inactivo']);
        DB::table('curso_requisito')->insert(['curso_id' => $curso->id, 'requisito_id' => $inactivo->id]);
        $otra = $this->record(Institucion::class, ['nombre' => 'Otra', 'slug' => 'otra']);
        $ajeno = $this->record(Requisito::class, ['institucion_id' => $otra->id, 'nombre' => 'REQUISITO-AJENO']);
        DB::table('curso_requisito')->insert(['curso_id' => $curso->id, 'requisito_id' => $ajeno->id]);
        $this->get('/cursos/'.$curso->id)->assertOk()->assertSee('Conocimientos básicos')->assertSee('Comprender conceptos iniciales.')
            ->assertSee('Obligatorio')->assertSee('Opcional')->assertSee('evidencia documental')->assertSee('requieren confirmación institucional')
            ->assertDontSee('REQUISITO-INACTIVO')->assertDontSee('REQUISITO-AJENO')->assertDontSee('ruta_evidencia');
    }

    public function test_horarios_no_exponen_enlaces_privados_ni_sesiones_canceladas(): void
    {
        $curso = $this->curso();
        $grupo = $this->grupo($this->edicion($curso));
        $datos = ['grupo_id' => $grupo->id, 'titulo' => 'TITULO-INTERNO', 'inicio_at' => '2026-11-24 15:00', 'fin_at' => '2026-11-24 16:00', 'enlace_virtual' => 'https://privado.example.test/clave-secreta'];
        $this->record(Sesion::class, $datos);
        $this->record(Sesion::class, ['estado' => 'cancelada', 'inicio_at' => '2026-11-25 18:00'] + $datos);
        $this->get('/cursos/'.$curso->id)->assertOk()->assertSee('24/11/2026 15:00')->assertSee('24/11/2026 16:00')
            ->assertDontSee('25/11/2026 18:00')->assertDontSee('TITULO-INTERNO')->assertDontSee('clave-secreta')->assertDontSee('privado.example.test');
    }

    public function test_identificadores_inexistentes_invalidos_y_consultas_privilegiadas_no_eluden_visibilidad(): void
    {
        $privado = $this->curso(['estado' => 'borrador']);
        foreach (['999999', '0', '-1', 'abc', '999999999999999999999999999', $privado->id.'?estado=publicado&institucion_id=1&withTrashed=1'] as $id) {
            $this->get('/cursos/'.$id)->assertNotFound();
        }
        $this->get('/cursos?estado=borrador&institucion_id=1&withTrashed=1')->assertOk()->assertDontSee($privado->titulo);
    }

    public function test_busqueda_y_filtros_se_aplican_conjuntamente_sobre_datos_publicos(): void
    {
        $curso = $this->curso();
        $this->edicion($curso, ['modalidad' => 'autogestionado']);
        $otro = $this->curso(['titulo' => 'Redes avanzadas']);
        $this->edicion($otro);
        $this->get('/cursos?q=Hardware&categoria='.$this->categoria->id.'&modalidad=autogestionado')->assertOk()->assertSee($curso->titulo)->assertDontSee($otro->titulo);
        $this->get('/cursos?modalidad=acompanada')->assertSee($otro->titulo)->assertDontSee($curso->titulo);
        $this->get('/cursos?categoria=9999')->assertOk()->assertSee('No hay cursos para mostrar')->assertDontSee($curso->titulo);
        $this->edicion($otro, ['modalidad' => 'autogestionado', 'estado' => 'borrador']);
        $this->get('/cursos?modalidad=autogestionado')->assertDontSee($otro->titulo);
    }

    public function test_filtros_solo_ofrecen_categorias_y_modalidades_con_cursos_publicos(): void
    {
        $curso = $this->curso();
        $this->edicion($curso, ['modalidad' => 'autogestionado']);
        $categoria = $this->record(CategoriaCurso::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'Categoría privada', 'slug' => 'privada']);
        $privado = $this->curso(['categoria_id' => $categoria->id, 'estado' => 'borrador']);
        $this->edicion($privado);
        $this->get('/cursos')->assertOk()->assertSee('Tecnología')->assertSee('Autogestionado')->assertDontSee('Categoría privada')->assertDontSee('Con acompañamiento');
    }

    public function test_paginacion_conserva_filtros_y_no_repite_registros(): void
    {
        for ($i = 1; $i <= 13; $i++) {
            $this->curso(['titulo' => sprintf('Curso %02d', $i)]);
        }
        $this->get('/cursos?q=Curso&categoria='.$this->categoria->id)->assertOk()->assertSee('Curso 01')->assertSee('Curso 12')->assertDontSee('Curso 13')
            ->assertSee('Página 1 de 2')->assertSee('q=Curso', false)->assertSee('page=2', false);
        $this->get('/cursos?q=Curso&categoria='.$this->categoria->id.'&page=2')->assertOk()->assertSee('Curso 13')->assertDontSee('Curso 01')->assertSee('Página 2 de 2');
    }

    public function test_catalogo_vacio_y_landing_sin_oferta_son_claros(): void
    {
        $this->get('/cursos')->assertOk()->assertSee('No hay cursos para mostrar')->assertSee('0 resultados');
        $this->get('/')->assertOk()->assertSee('Todavía no hay cursos publicados.')->assertSee('Ver todos los cursos')->assertSee('href="'.route('cursos.index').'"', false);
    }

    public function test_html_se_escapa_en_textos_atributos_y_busquedas(): void
    {
        $html = '<script>alert("xss")</script>';
        $curso = $this->curso(['titulo' => $html, 'descripcion' => '<img src=x onerror=alert(1)>', 'objetivos' => $html]);
        $this->institucion->update(['nombre' => $html]);
        $this->categoria->update(['nombre' => $html]);
        foreach (['/cursos', '/cursos/'.$curso->id, '/'] as $url) {
            $this->get($url)->assertOk()->assertSee(e($html), false)->assertDontSee($html, false)->assertDontSee('<img src=x onerror=', false);
        }
        $this->get('/cursos?q='.urlencode('"><script>alert(1)</script>'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_busquedas_sql_y_comodines_no_amplian_resultados(): void
    {
        $curso = $this->curso();
        foreach (["' OR 1=1 --", '%', '_'] as $texto) {
            $this->get('/cursos?q='.urlencode($texto))->assertOk()->assertDontSee($curso->titulo)->assertSee('0 resultados');
        }
        $literal = $this->curso(['titulo' => 'Avance 100%_real!']);
        $this->get('/cursos?q='.urlencode('100%_real!'))->assertOk()->assertSee($literal->titulo)->assertDontSee($curso->titulo);
    }

    public function test_filtros_invalidos_se_rechazan_antes_de_consultar(): void
    {
        foreach ([['q' => ['texto']], ['q' => str_repeat('x', 181)], ['categoria' => '1 OR 1=1'], ['modalidad' => 'presencial'], ['page' => -1], ['modalidad' => ['autogestionado']]] as $query) {
            $this->getJson('/cursos?'.http_build_query($query))->assertUnprocessable();
        }
    }

    public function test_inicio_muestra_solo_tres_cursos_publicados_y_enlaces_funcionales(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $curso = $this->curso(['titulo' => 'Curso público '.$i]);
        }
        $this->curso(['titulo' => 'Borrador secreto', 'estado' => 'borrador']);
        $this->get('/')->assertOk()->assertSee('Curso público 4')->assertSee('Curso público 3')->assertSee('Curso público 2')->assertDontSee('Curso público 1')
            ->assertDontSee('Borrador secreto')->assertSee('href="'.route('cursos.show', $curso->id).'"', false)
            ->assertSee('Ver todos los cursos')->assertSee('Explorar cursos')->assertDontSee('El catálogo aún no está disponible');
    }

    public function test_consultas_del_catalogo_no_crecen_por_tarjeta_ni_cargan_datos_privados(): void
    {
        $this->grupo($this->edicion($this->curso()));
        DB::enableQueryLog();
        $this->get('/cursos')->assertOk();
        $una = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($i = 2; $i <= 12; $i++) {
            $this->grupo($this->edicion($this->curso(['titulo' => 'Curso '.$i])));
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/cursos')->assertOk();
        $consultas = DB::getQueryLog();
        DB::disableQueryLog();
        self::assertSame($una, count($consultas));
        self::assertLessThanOrEqual(12, count($consultas));
        foreach ($consultas as $consulta) {
            self::assertDoesNotMatchRegularExpression('/\b(?:inscripciones|participantes|enlace_virtual|pagos|usuario_rol)\b/', $consulta['query']);
        }
    }
}
