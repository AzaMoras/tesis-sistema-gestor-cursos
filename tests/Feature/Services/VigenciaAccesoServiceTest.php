<?php

namespace Tests\Feature\Services;

use App\Models\Bitacora;
use App\Models\GrupoInstructor;
use App\Models\Inscripcion;
use App\Models\Instructor;
use App\Services\EvaluacionService;
use App\Services\VigenciaAccesoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Tests\Support\ServiceTestCase;

class VigenciaAccesoServiceTest extends ServiceTestCase
{
    private function configurar(?string $tipo = 'con_vencimiento', ?int $dias = 30, ?int $prorroga = 14): void
    {
        $this->grupo->edicion->forceFill([
            'tipo_vigencia' => $tipo, 'duracion_acceso_dias' => $dias, 'prorroga_gratuita_dias' => $prorroga,
        ])->save();
        $this->grant($this->admin, 'inscripciones.gestionar');
    }

    public function test_ta_08_ta_09_activation_snapshots_server_dates_and_retry_does_not_extend_access(): void
    {
        $this->configurar();
        $enrollment = $this->enrollment();
        $service = $this->app->make(VigenciaAccesoService::class);
        $active = $service->activar($this->admin, $this->institucion->id, $enrollment->id);
        self::assertTrue($active->acceso_inicia_at->eq(now()));
        self::assertTrue($active->acceso_vence_at->eq(now()->addDays(30)));
        self::assertSame(14, $active->prorroga_gratuita_dias);
        $this->travel(1)->days();
        $this->grupo->edicion->forceFill(['duracion_acceso_dias' => 60, 'prorroga_gratuita_dias' => 7])->save();
        $retry = $service->activar($this->admin, $this->institucion->id, $enrollment->id);
        self::assertTrue($active->acceso_vence_at->eq($retry->acceso_vence_at));
        self::assertSame(14, $retry->prorroga_gratuita_dias);
        self::assertSame(1, Bitacora::where('accion', 'vigencia.activar')->count());
        self::assertSame($enrollment->id, $service->exigirPuedeIniciar($this->participante->usuario, $this->institucion->id, $enrollment->id)->id);
    }

    public function test_ta_10_ta_13_expiration_blocks_new_actions_without_changing_history_or_creating_charges(): void
    {
        $this->configurar();
        $enrollment = $this->enrollment();
        $submission = $this->submission($enrollment);
        $this->grant($this->admin, 'evaluaciones.calificar');
        $evaluation = $this->app->make(EvaluacionService::class)->registrar($this->admin, $this->institucion->id, $submission->id, '90');
        $service = $this->app->make(VigenciaAccesoService::class);
        $active = $service->activar($this->admin, $this->institucion->id, $enrollment->id);
        $before = $active->getRawOriginal();
        $this->travelTo($active->acceso_vence_at);
        $this->rejects(fn () => $service->exigirPuedeIniciar($this->participante->usuario, $this->institucion->id, $enrollment->id), ValidationException::class, 'vigencia');
        self::assertSame($before, $active->fresh()->getRawOriginal());
        self::assertSame('90.00', $evaluation->fresh()->calificacion);
        $this->assertDatabaseHas('entregas', ['id' => $submission->id]);
        $this->assertDatabaseCount('pagos', 0);
        self::assertSame($enrollment->id, $this->app->make(EvaluacionService::class)->desglose($this->participante->usuario, $this->institucion->id, $enrollment->id)['inscripcion_id']);
    }

    public function test_ta_11_extension_is_explicit_once_and_audited_with_previous_expiration(): void
    {
        $this->configurar();
        $enrollment = $this->enrollment();
        $service = $this->app->make(VigenciaAccesoService::class);
        $active = $service->activar($this->admin, $this->institucion->id, $enrollment->id);
        $old = $active->acceso_vence_at;
        $extended = $service->aplicarProrrogaGratuita($this->participante->usuario, $this->institucion->id, $enrollment->id);
        self::assertTrue($extended->acceso_vence_at->eq($old->addDays(14)));
        $this->rejects(fn () => $service->aplicarProrrogaGratuita($this->participante->usuario, $this->institucion->id, $enrollment->id), ValidationException::class, 'prorroga');
        $audit = Bitacora::where('accion', 'vigencia.prorroga_gratuita')->sole();
        self::assertSame($old->toIso8601String(), $audit->detalles_json['vencimiento_anterior']);
        self::assertSame($extended->acceso_vence_at->toIso8601String(), $audit->detalles_json['vencimiento_nuevo']);
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_ta_11_extension_after_expiration_starts_at_explicit_acceptance(): void
    {
        $this->configurar();
        $enrollment = $this->enrollment();
        $service = $this->app->make(VigenciaAccesoService::class);
        $active = $service->activar($this->admin, $this->institucion->id, $enrollment->id);
        $this->travelTo($active->acceso_vence_at->addDays(20));
        $extended = $service->aplicarProrrogaGratuita($this->participante->usuario, $this->institucion->id, $enrollment->id);
        self::assertTrue($extended->acceso_vence_at->eq(now()->addDays(14)));
    }

    public function test_ta_12_permanent_access_never_gets_thirty_day_expiration(): void
    {
        $this->configurar('sin_vencimiento', null, null);
        $enrollment = $this->enrollment();
        $service = $this->app->make(VigenciaAccesoService::class);
        self::assertNull($service->activar($this->admin, $this->institucion->id, $enrollment->id)->acceso_vence_at);
        $this->travel(365)->days();
        self::assertSame($enrollment->id, $service->exigirPuedeIniciar($this->participante->usuario, $this->institucion->id, $enrollment->id)->id);
        $this->rejects(fn () => $service->aplicarProrrogaGratuita($this->participante->usuario, $this->institucion->id, $enrollment->id), ValidationException::class, 'prorroga');
    }

    public function test_ta_28_foreign_owner_institution_and_unprivileged_activation_are_denied(): void
    {
        $this->configurar();
        $enrollment = $this->enrollment();
        $service = $this->app->make(VigenciaAccesoService::class);
        $this->rejects(fn () => $service->activar($this->participante->usuario, $this->institucion->id, $enrollment->id), AuthorizationException::class);
        $service->activar($this->admin, $this->institucion->id, $enrollment->id);
        $other = $this->participant();
        $this->rejects(fn () => $service->exigirPuedeIniciar($other->usuario, $this->institucion->id, $enrollment->id), AuthorizationException::class);
        $this->rejects(fn () => $service->aplicarProrrogaGratuita($other->usuario, $this->institucion->id, $enrollment->id), AuthorizationException::class);
        $foreign = $this->userWithRole('administrador', $this->otraInstitucion);
        $this->grant($foreign, 'inscripciones.gestionar', $this->otraInstitucion);
        $this->rejects(fn () => $service->activar($foreign, $this->otraInstitucion->id, $enrollment->id), ModelNotFoundException::class);
        self::assertNull($enrollment->fresh()->prorroga_gratuita_aplicada_at);
    }

    public function test_legacy_policy_is_not_assumed_and_unactivated_or_unconfirmed_access_is_denied(): void
    {
        $this->grant($this->admin, 'inscripciones.gestionar');
        $enrollment = $this->enrollment();
        $service = $this->app->make(VigenciaAccesoService::class);
        $this->rejects(fn () => $service->activar($this->admin, $this->institucion->id, $enrollment->id), ValidationException::class, 'vigencia');
        $this->rejects(fn () => $service->exigirPuedeIniciar($this->participante->usuario, $this->institucion->id, $enrollment->id), ValidationException::class, 'vigencia');
        $this->configurar();
        $enrollment->forceFill(['estado' => 'pendiente_pago'])->save();
        $this->rejects(fn () => $service->activar($this->admin, $this->institucion->id, $enrollment->id), ValidationException::class, 'inscripcion');
        $this->assertDatabaseMissing('bitacora', ['accion' => 'vigencia.activar']);
    }

    public function test_disabled_extension_and_future_or_cancelled_access_are_denied(): void
    {
        $this->configurar(prorroga: null);
        $enrollment = $this->enrollment();
        $service = $this->app->make(VigenciaAccesoService::class);
        $service->activar($this->admin, $this->institucion->id, $enrollment->id);
        $this->rejects(fn () => $service->aplicarProrrogaGratuita($this->participante->usuario, $this->institucion->id, $enrollment->id), ValidationException::class, 'prorroga');
        $enrollment->refresh()->forceFill(['acceso_inicia_at' => now()->addDay()])->save();
        $this->rejects(fn () => $service->exigirPuedeIniciar($this->participante->usuario, $this->institucion->id, $enrollment->id), ValidationException::class, 'vigencia');
        $enrollment->forceFill(['fecha_cancelacion' => now()])->save();
        $this->rejects(fn () => $service->exigirPuedeIniciar($this->participante->usuario, $this->institucion->id, $enrollment->id), ValidationException::class, 'inscripcion');
    }

    public function test_access_fields_cannot_be_mass_assigned(): void
    {
        $enrollment = new Inscripcion;
        $this->expectException(MassAssignmentException::class);
        $enrollment->fill(['acceso_inicia_at' => now(), 'acceso_vence_at' => null, 'prorroga_gratuita_dias' => 90]);
    }

    public function test_ta_24_instructor_with_management_permission_still_requires_assignment(): void
    {
        $this->configurar();
        $enrollment = $this->enrollment();
        $user = $this->userWithRole('instructor');
        $instructor = $this->record(Instructor::class, ['user_id' => $user->id, 'especialidad' => 'Capacitación virtual']);
        $this->grant($user, 'inscripciones.gestionar', role: 'instructor');
        $service = $this->app->make(VigenciaAccesoService::class);
        $this->rejects(fn () => $service->activar($user, $this->institucion->id, $enrollment->id), AuthorizationException::class);
        $service->activar($this->admin, $this->institucion->id, $enrollment->id);
        $this->rejects(fn () => $service->exigirPuedeIniciar($user, $this->institucion->id, $enrollment->id), AuthorizationException::class);
        $this->rejects(fn () => $service->aplicarProrrogaGratuita($user, $this->institucion->id, $enrollment->id), AuthorizationException::class);
        $this->record(GrupoInstructor::class, ['grupo_id' => $this->grupo->id, 'instructor_id' => $instructor->id]);
        self::assertSame($enrollment->id, $service->exigirPuedeIniciar($user, $this->institucion->id, $enrollment->id)->id);
    }
}
