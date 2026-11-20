<?php

namespace Tests\Feature;

use App\Models\Edicion;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Services\GestionOfertaService;
use App\Services\Soporte\ReglasOperacion;
use Illuminate\Support\Carbon;
use Tests\Support\ServiceTestCase;

class GestionFechasEdicionTest extends ServiceTestCase
{
    private Edicion $edicion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-19 22:30:15', 'UTC'));
        $this->grant($this->admin, 'cursos.gestionar');
        $this->actingAs($this->admin);
        $this->edicion = $this->grupo->edicion;
        $this->edicion->forceFill(['estado' => 'borrador'])->save();
        $this->grupo->forceFill(['estado' => 'borrador'])->save();
    }

    private function url(string $sufijo = ''): string
    {
        return '/gestion/'.$this->institucion->id.'/ediciones'.$sufijo;
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + ['curso_id' => $this->edicion->curso_id, 'clave' => 'ED-FECHAS', 'modalidad' => 'autogestionado',
            'fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-30', 'cierre_inscripciones_at' => '2026-11-30T22:30',
            'precio' => '0.00', 'moneda' => 'MXN'];
    }

    private function originales(): array
    {
        return array_intersect_key(app(GestionOfertaService::class)->datosDe('ediciones', $this->edicion->fresh()), $this->datos());
    }

    private function historica(): void
    {
        $this->edicion->forceFill(['fecha_inicio' => '2026-11-01', 'fecha_fin' => '2026-11-10', 'cierre_inscripciones_at' => '2026-11-09 12:15:37'])->save();
    }

    public function test_nuevas_ediciones_rechazan_fechas_pasadas_y_final_anterior_al_inicio(): void
    {
        foreach ([['fecha_inicio' => '2026-11-18'], ['fecha_inicio' => '2026-11-19', 'fecha_fin' => '2026-11-18'], ['fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-19']] as $cambio) {
            $campo = array_key_exists('fecha_fin', $cambio) ? 'fecha_fin' : 'fecha_inicio';
            $this->post($this->url(), $this->datos($cambio))->assertSessionHasErrors($campo);
        }
        $this->assertDatabaseCount('ediciones', 1);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_inicio_y_fin_hoy_con_cierre_futuro_el_mismo_dia_son_validos(): void
    {
        $this->post($this->url(), $this->datos(['fecha_inicio' => '2026-11-19', 'fecha_fin' => '2026-11-19', 'cierre_inscripciones_at' => '2026-11-19T23:59']))
            ->assertSessionHasNoErrors()->assertRedirect($this->url());
        $edicion = Edicion::latest('id')->first();
        self::assertSame('2026-11-19', $edicion->fecha_inicio->format('Y-m-d'));
        self::assertSame('2026-11-19', $edicion->fecha_fin->format('Y-m-d'));
        self::assertSame('2026-11-19 23:59:00', $edicion->cierre_inscripciones_at->format('Y-m-d H:i:s'));
    }

    public function test_fechas_futuras_y_cierre_nulo_son_validos(): void
    {
        $this->post($this->url(), $this->datos(['cierre_inscripciones_at' => null]))->assertSessionHasNoErrors()->assertRedirect();
        self::assertNull(Edicion::latest('id')->first()->cierre_inscripciones_at);
    }

    public function test_cierre_pasado_o_igual_al_instante_actual_se_rechaza(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 22:30:00', 'UTC'));
        foreach (['2026-11-18T23:59', '2026-11-19T22:29', '2026-11-19T22:30'] as $cierre) {
            $this->post($this->url(), $this->datos(['cierre_inscripciones_at' => $cierre]))->assertSessionHasErrors('cierre_inscripciones_at');
        }
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_cierre_en_cualquier_minuto_del_dia_final_es_valido_y_el_dia_siguiente_no(): void
    {
        foreach (['00:00', '12:00', '23:59'] as $hora) {
            $this->post($this->url(), $this->datos(['clave' => 'ED-'.$hora, 'cierre_inscripciones_at' => '2026-11-30T'.$hora]))->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->post($this->url(), $this->datos(['cierre_inscripciones_at' => '2026-12-01T00:00']))->assertSessionHasErrors('cierre_inscripciones_at');
        $this->assertDatabaseCount('ediciones', 4);
    }

    public function test_formatos_imposibles_y_payloads_manipulados_no_se_normalizan_silenciosamente(): void
    {
        foreach (['2026-02-30', '2026-13-01', '19/11/2026', '11/19/2026', ['2026-11-20']] as $inicio) {
            $this->post($this->url(), $this->datos(['fecha_inicio' => $inicio]))->assertSessionHasErrors('fecha_inicio');
        }
        foreach (['2026-11-30T24:00', '2026-11-30T23:60', '2026-11-30T22:30:00', '2026-11-30T22:30Z'] as $cierre) {
            $this->post($this->url(), $this->datos(['cierre_inscripciones_at' => $cierre]))->assertSessionHasErrors('cierre_inscripciones_at');
        }
        $this->assertDatabaseCount('ediciones', 1);
    }

    public function test_texto_mexicano_se_convierte_a_iso_sin_javascript_y_sin_confiar_en_el_oculto(): void
    {
        $this->post($this->url(), $this->datos(['fecha_inicio' => '1999-01-01', 'fecha_inicio_mexicana' => '20/11/2026',
            'fecha_fin_mexicana' => '30/11/2026', 'cierre_inscripciones_at_mexicana' => '30/11/2026 22:30']))->assertSessionHasNoErrors()->assertRedirect();
        $edicion = Edicion::latest('id')->first();
        self::assertSame('2026-11-20', $edicion->fecha_inicio->format('Y-m-d'));
        self::assertSame('2026-11-30 22:30:00', $edicion->cierre_inscripciones_at->format('Y-m-d H:i:s'));
    }

    public function test_texto_mexicano_invalido_y_formato_estadounidense_mantienen_el_valor_y_el_error_junto_al_campo(): void
    {
        foreach (['31/11/2026', '11/20/2026', '20/11/26', ['20/11/2026']] as $visible) {
            $this->from($this->url('/crear'))->post($this->url(), $this->datos(['fecha_inicio_mexicana' => $visible]))
                ->assertSessionHasErrors('fecha_inicio')->assertSessionHasInput('fecha_inicio_mexicana', $visible);
        }
        $this->followingRedirects()->from($this->url('/crear'))->post($this->url(), $this->datos(['fecha_inicio_mexicana' => '31/11/2026']))
            ->assertOk()->assertSee('value="31/11/2026"', false)->assertSee('fecha_inicio-error', false)->assertSee('dd/mm/aaaa');
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_limites_visuales_proceden_del_servidor_y_no_usan_controles_date_localizados(): void
    {
        $this->get($this->url('/crear'))->assertOk()->assertSee('data-hoy="2026-11-19"', false)->assertSee('data-min-cierre="2026-11-19T22:31"', false)
            ->assertSee('name="fecha_inicio"', false)->assertSee('name="fecha_inicio_mexicana"', false)->assertSee('type="hidden"', false)
            ->assertSee('dd/mm/aaaa HH:mm')->assertSee('tiempo universal coordinado')->assertSee('Abrir calendario de fecha inicial')
            ->assertDontSee('type="date"', false)->assertDontSee('type="datetime-local"', false)->assertDontSee('00:00 de la fecha final');
    }

    public function test_historia_editable_conserva_fechas_pasadas_incluso_sus_segundos(): void
    {
        $this->historica();
        $antes = $this->edicion->fresh()->only(['fecha_inicio', 'fecha_fin', 'cierre_inscripciones_at']);
        $this->put($this->url('/'.$this->edicion->id), array_replace($this->originales(), ['clave' => 'CORREGIDA']))->assertSessionHasNoErrors()->assertRedirect();
        $despues = $this->edicion->fresh();
        self::assertEquals($antes, $despues->only(['fecha_inicio', 'fecha_fin', 'cierre_inscripciones_at']));
        self::assertSame('2026-11-09 12:15:37', $despues->cierre_inscripciones_at->format('Y-m-d H:i:s'));
        $this->get($this->url('/'.$this->edicion->id.'/editar'))->assertOk()->assertSee('value="01/11/2026"', false)->assertSee('value="09/11/2026 12:15"', false);
    }

    public function test_put_rechaza_nuevas_fechas_pasadas_sobre_un_borrador_historico(): void
    {
        $this->historica();
        foreach (['fecha_inicio' => '2026-11-18', 'fecha_fin' => '2026-11-18', 'cierre_inscripciones_at' => '2026-11-18T12:00'] as $campo => $valor) {
            $this->put($this->url('/'.$this->edicion->id), array_replace($this->originales(), [$campo => $valor]))->assertSessionHasErrors($campo);
        }
        $this->assertDatabaseCount('bitacora', 0);
        self::assertSame('2026-11-01', $this->edicion->fresh()->fecha_inicio->format('Y-m-d'));
    }

    public function test_cambiar_fin_revalida_el_rango_del_cierre_aunque_el_cierre_no_cambie(): void
    {
        $this->edicion->forceFill(['fecha_inicio' => '2026-11-20', 'fecha_fin' => '2026-11-30', 'cierre_inscripciones_at' => '2026-11-30 22:30:00'])->save();
        $this->put($this->url('/'.$this->edicion->id), array_replace($this->originales(), ['fecha_fin' => '2026-11-29']))->assertSessionHasErrors('cierre_inscripciones_at');
        self::assertSame('2026-11-30', $this->edicion->fresh()->fecha_fin->format('Y-m-d'));
    }

    public function test_se_puede_conservar_inicio_historico_y_corregir_fin_y_cierre_hacia_el_futuro(): void
    {
        $this->historica();
        $this->put($this->url('/'.$this->edicion->id), array_replace($this->originales(), ['fecha_fin' => '2026-11-30', 'cierre_inscripciones_at' => '2026-11-30T23:59']))->assertSessionHasNoErrors()->assertRedirect();
        self::assertSame('2026-11-01', $this->edicion->fresh()->fecha_inicio->format('Y-m-d'));
    }

    public function test_apertura_rechaza_fin_vencido_y_cierre_vencido_sin_cambiar_estado(): void
    {
        $this->historica();
        $this->post($this->url('/'.$this->edicion->id.'/publicar'))->assertSessionHasErrors('fecha_fin');
        $this->edicion->forceFill(['fecha_fin' => '2026-11-30'])->save();
        $this->post($this->url('/'.$this->edicion->id.'/publicar'))->assertSessionHasErrors('cierre_inscripciones_at');
        self::assertSame('borrador', $this->edicion->fresh()->estado);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_apertura_admite_inicio_pasado_con_fin_y_cierre_vigentes_y_preserva_segundos(): void
    {
        $this->edicion->forceFill(['fecha_inicio' => '2026-11-18', 'fecha_fin' => '2026-11-19', 'cierre_inscripciones_at' => '2026-11-19 22:30:50'])->save();
        $this->post($this->url('/'.$this->edicion->id.'/publicar'))->assertSessionHasNoErrors()->assertRedirect();
        self::assertSame('abierta', $this->edicion->fresh()->estado);
        self::assertSame('2026-11-19 22:30:50', $this->edicion->fresh()->cierre_inscripciones_at->format('Y-m-d H:i:s'));
    }

    public function test_los_limites_se_recalculan_al_guardar_aunque_el_formulario_se_abrio_antes(): void
    {
        $this->get($this->url('/crear'))->assertOk()->assertSee('data-min-cierre="2026-11-19T22:31"', false);
        $this->travelTo(Carbon::parse('2026-11-19 22:32:00', 'UTC'));
        $this->post($this->url(), $this->datos(['cierre_inscripciones_at' => '2026-11-19T22:31']))->assertSessionHasErrors('cierre_inscripciones_at');
    }

    public function test_ediciones_abiertas_o_con_inscripciones_siguen_protegidas(): void
    {
        $this->edicion->forceFill(['estado' => 'abierta'])->save();
        $this->put($this->url('/'.$this->edicion->id), $this->datos())->assertSessionHasErrors('estado');
        $this->edicion->forceFill(['estado' => 'borrador'])->save();
        $this->enrollment();
        $this->put($this->url('/'.$this->edicion->id), $this->datos())->assertSessionHasErrors('estado');
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_detalle_y_listado_de_gestion_muestran_formato_mexicano_sin_iso_visible(): void
    {
        $this->edicion->forceFill(['fecha_inicio' => '2026-11-19', 'fecha_fin' => '2026-11-30', 'cierre_inscripciones_at' => '2026-11-30 22:30:00'])->save();
        $this->get($this->url('/'.$this->edicion->id))->assertOk()->assertSee('19/11/2026')->assertSee('30/11/2026 22:30')
            ->assertDontSee('2026-11-19')->assertDontSee('11/19/2026');
        $this->get($this->url())->assertOk()->assertSee('19/11/2026 a 30/11/2026');
    }

    public function test_edicion_de_hoy_se_publica_y_permite_inscripcion_gratuita_con_fechas_mexicanas(): void
    {
        $this->post($this->url(), $this->datos(['fecha_inicio_mexicana' => '19/11/2026', 'fecha_fin_mexicana' => '19/11/2026', 'cierre_inscripciones_at_mexicana' => '19/11/2026 23:59']))->assertSessionHasNoErrors()->assertRedirect();
        $edicion = Edicion::latest('id')->first();
        $this->post($this->url('/'.$edicion->id.'/publicar'))->assertSessionHasNoErrors();
        $grupo = $this->record(Grupo::class, ['edicion_id' => $edicion->id, 'codigo' => 'FECHAS-GRATUITO', 'cupo_maximo' => 2, 'estado' => 'abierta']);
        $this->app->instance(ReglasOperacion::class, new ReglasOperacion(estados: config('operacion.estados'), apertura: config('operacion.apertura'), cupoNuloIlimitado: false));
        $this->actingAs($this->participante->usuario)->get('/cursos/'.$edicion->curso_id)->assertOk()->assertSee('19/11/2026 23:59')->assertSee('Inscribirme gratis')->assertSee('tiempo universal coordinado');
        $this->post('/cursos/'.$edicion->curso_id.'/grupos/'.$grupo->id.'/inscripcion', ['confirmacion' => '1'])->assertRedirect('/mis-cursos');
        $this->get('/mis-cursos')->assertOk()->assertSee('19/11/2026 22:30')->assertSee('confirmada')->assertSee('tiempo universal coordinado');
        self::assertSame('0.00', Inscripcion::where('grupo_id', $grupo->id)->sole()->precio_acordado);
        self::assertSame('UTC', config('app.timezone'));
    }
}
