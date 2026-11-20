<?php

namespace Tests\Feature\Services;

use App\Models\Bitacora;
use App\Models\CategoriaCurso;
use App\Models\Curso;
use App\Models\CursoRequisito;
use App\Models\Edicion;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Institucion;
use App\Models\ParticipanteRequisito;
use App\Models\Requisito;
use App\Models\UsuarioRol;
use App\Services\InscripcionService;
use App\Services\Soporte\DecisionOperacionPendiente;
use App\Services\Soporte\ReglasOperacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\InscripcionPublicaTestCase;

class InscripcionGratuitaServiceTest extends InscripcionPublicaTestCase
{
    private function inscribir(): Inscripcion
    {
        return app(InscripcionService::class)->inscribirGratuita($this->usuario, $this->curso->id, $this->grupo->id);
    }

    public function test_vinculacion_inscripcion_y_auditorias_se_guardan_juntas(): void
    {
        $inscripcion = $this->inscribir();
        self::assertSame($this->usuario->participante->id, $inscripcion->participante_id);
        self::assertSame('confirmada', $inscripcion->estado);
        self::assertSame('0.00', $inscripcion->precio_acordado);
        self::assertTrue($inscripcion->fecha_inscripcion->eq(now()));
        self::assertNull($inscripcion->reserva_vence_at);
        self::assertNull($inscripcion->acceso_inicia_at);
        self::assertNull($inscripcion->acceso_vence_at);
        $this->assertDatabaseHas('usuario_rol', ['user_id' => $this->usuario->id, 'rol_id' => $this->rol->id,
            'institucion_id' => $this->institucion->id, 'asignado_por' => $this->usuario->id]);
        $this->assertDatabaseHas('bitacora', ['accion' => 'usuario_rol.autovincular_participante', 'usuario_id' => $this->usuario->id]);
        $this->assertDatabaseHas('bitacora', ['accion' => 'inscripciones.crear', 'usuario_id' => $this->usuario->id, 'registro_id' => $inscripcion->id]);
        $this->assertDatabaseCount('roles', 1);
        $this->assertDatabaseCount('rol_permiso', 0);
    }

    public function test_asignacion_existente_se_reutiliza_y_no_se_reescribe_asignado_por(): void
    {
        $this->record(UsuarioRol::class, ['user_id' => $this->usuario->id, 'rol_id' => $this->rol->id,
            'institucion_id' => $this->institucion->id, 'asignado_por' => null]);
        $this->inscribir();
        $this->assertDatabaseCount('usuario_rol', 1);
        self::assertNull(UsuarioRol::sole()->asignado_por);
        $this->assertDatabaseCount('bitacora', 1);
    }

    public function test_fallo_de_auditoria_revierte_tambien_vinculacion_y_no_se_oculta(): void
    {
        Bitacora::creating(function (Bitacora $registro): void {
            if ($registro->accion === 'inscripciones.crear') {
                throw new RuntimeException('Fallo técnico simulado de auditoría');
            }
        });
        try {
            try {
                $this->inscribir();
                self::fail('Debió propagarse el fallo técnico.');
            } catch (RuntimeException $error) {
                self::assertSame('Fallo técnico simulado de auditoría', $error->getMessage());
            }
        } finally {
            Event::forget('eloquent.creating: '.Bitacora::class);
        }
        $this->assertDatabaseCount('usuario_rol', 0);
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_cancelacion_historica_bloquea_reinscripcion_y_conserva_el_registro(): void
    {
        $inscripcion = $this->inscribir();
        $inscripcion->forceFill(['estado' => 'cancelada', 'fecha_cancelacion' => now()])->save();
        try {
            $this->inscribir();
            self::fail('No se permite recrear una inscripción cancelada.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('inscripcion', $error->errors());
        }
        $this->assertDatabaseCount('inscripciones', 1);
        self::assertSame('cancelada', $inscripcion->fresh()->estado);
    }

    public function test_ultimo_cupo_se_asigna_una_vez_con_solicitudes_sucesivas(): void
    {
        $this->grupo->forceFill(['cupo_maximo' => 1])->save();
        $this->inscribir();
        $otro = $this->nuevoParticipante();
        try {
            app(InscripcionService::class)->inscribirGratuita($otro, $this->curso->id, $this->grupo->id);
            self::fail('El último cupo ya fue utilizado.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('cupo', $error->errors());
        }
        $this->assertDatabaseCount('inscripciones', 1);
        $this->assertDatabaseMissing('usuario_rol', ['user_id' => $otro->id]);
    }

    public function test_reservas_vigentes_ocupan_cupo_y_vencidas_no_sin_habilitar_pagos(): void
    {
        $otro = $this->nuevoParticipante();
        $this->grupo->forceFill(['cupo_maximo' => 1])->save();
        $reserva = $this->record(Inscripcion::class, ['participante_id' => $otro->participante->id, 'grupo_id' => $this->grupo->id,
            'estado' => 'pendiente_pago', 'reserva_vence_at' => now()->addMinute(), 'precio_acordado' => '100.00']);
        try {
            $this->inscribir();
            self::fail('Una reserva vigente ocupa el cupo.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('cupo', $error->errors());
        }
        $reserva->forceFill(['reserva_vence_at' => now()])->save();
        self::assertSame('confirmada', $this->inscribir()->estado);
        self::assertSame('pendiente_pago', $reserva->fresh()->estado);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_requisito_validado_necesita_politica_explicita_y_revision_valida(): void
    {
        $requisito = $this->record(Requisito::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'Previo', 'requiere_documento' => true]);
        $this->record(CursoRequisito::class, ['curso_id' => $this->curso->id, 'requisito_id' => $requisito->id, 'obligatorio' => true]);
        $this->record(ParticipanteRequisito::class, ['participante_id' => $this->usuario->participante->id, 'requisito_id' => $requisito->id,
            'estado' => 'validado', 'fecha_validacion' => now(), 'revisado_por' => $this->usuario->id, 'ruta_evidencia' => 'fixtures/privada.pdf']);
        try {
            $this->inscribir();
            self::fail('No se debe suponer un estado normativo a partir del fixture.');
        } catch (DecisionOperacionPendiente) {
            $this->assertDatabaseCount('usuario_rol', 0);
        }
        // Convención explícita exclusiva de esta prueba; no configura producción.
        $this->app->instance(ReglasOperacion::class, new ReglasOperacion(
            estados: config('operacion.estados') + ['requisito.validado' => 'validado'],
            apertura: config('operacion.apertura'), cupoNuloIlimitado: false));
        self::assertSame('confirmada', $this->inscribir()->estado);
    }

    public function test_requisito_inactivo_o_de_otra_institucion_nunca_se_omite(): void
    {
        $requisito = $this->record(Requisito::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'Inactivo', 'estado' => 'inactivo']);
        $this->record(CursoRequisito::class, ['curso_id' => $this->curso->id, 'requisito_id' => $requisito->id, 'obligatorio' => true]);
        try {
            $this->inscribir();
            self::fail('El requisito inactivo necesita una decisión institucional.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('requisitos', $error->errors());
        }
        $otra = $this->record(Institucion::class, ['nombre' => 'Otra', 'slug' => 'otra-requisitos']);
        $requisito->forceFill(['estado' => 'activo', 'institucion_id' => $otra->id])->save();
        try {
            $this->inscribir();
            self::fail('No se deben aceptar requisitos de otra institución.');
        } catch (ValidationException $error) {
            self::assertArrayHasKey('requisitos', $error->errors());
        }
        $this->assertDatabaseCount('usuario_rol', 0);
    }

    public function test_funciona_sin_columnas_de_vigencia_y_no_intenta_activarlas(): void
    {
        self::assertSame('sqlite', DB::getDefaultConnection());
        self::assertSame(':memory:', DB::connection()->getDatabaseName());
        // Solo se elimina la migración de vigencia de esta SQLite efímera.
        $migracion = require database_path('migrations/2026_11_10_000001_add_vigencia_to_ediciones_and_inscripciones.php');
        $migracion->down();
        DB::enableQueryLog();
        try {
            $inscripcion = $this->inscribir();
            self::assertSame('confirmada', $inscripcion->estado);
            $this->actingAs($this->usuario)->get('/mis-cursos')->assertOk()->assertSee($this->curso->titulo);
            foreach (DB::getQueryLog() as $consulta) {
                self::assertDoesNotMatchRegularExpression('/(?:acceso_inicia_at|acceso_vence_at|tipo_vigencia|prorroga_gratuita)/', $consulta['query']);
            }
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_institucion_se_deriva_del_grupo_elegido_sin_asignar_una_fija(): void
    {
        $otra = $this->record(Institucion::class, ['nombre' => 'Otra institución', 'slug' => 'otra']);
        $categoria = $this->record(CategoriaCurso::class, ['institucion_id' => $otra->id, 'nombre' => 'Otra categoría', 'slug' => 'otra']);
        $curso = $this->record(Curso::class, ['institucion_id' => $otra->id, 'categoria_id' => $categoria->id, 'titulo' => 'Otro curso público', 'descripcion' => 'Curso de otra institución', 'estado' => 'publicado']);
        $edicion = $this->record(Edicion::class, ['curso_id' => $curso->id, 'clave' => 'OTRA', 'modalidad' => 'autogestionado', 'fecha_inicio' => today()->addDay(), 'fecha_fin' => today()->addMonth(), 'precio' => '0.00', 'moneda' => 'MXN', 'estado' => 'abierta']);
        $grupo = $this->record(Grupo::class, ['edicion_id' => $edicion->id, 'codigo' => 'OTRO', 'cupo_maximo' => 1, 'estado' => 'abierta']);
        app(InscripcionService::class)->inscribirGratuita($this->usuario, $curso->id, $grupo->id);
        $this->assertDatabaseHas('usuario_rol', ['user_id' => $this->usuario->id, 'rol_id' => $this->rol->id, 'institucion_id' => $otra->id]);
        $this->assertDatabaseMissing('usuario_rol', ['user_id' => $this->usuario->id, 'institucion_id' => $this->institucion->id]);
        $this->assertDatabaseCount('usuario_rol', 1);
    }
}
