<?php

namespace Tests\Feature\Services;

use App\Models\Bitacora;
use App\Models\Evaluacion;
use App\Models\GrupoInstructor;
use App\Models\Instructor;
use App\Models\ModuloCurso;
use App\Services\EvaluacionService;
use App\Services\Soporte\ReglasOperacion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Tests\Support\ServiceTestCase;

class EvaluacionServiceTest extends ServiceTestCase
{
    public function test_manual_grade_records_reviewer_score_and_audit(): void
    {
        $this->grant($this->admin, 'evaluaciones.calificar');
        $submission = $this->submission();
        $evaluation = $this->app->make(EvaluacionService::class)->registrar($this->admin, $this->institucion->id, $submission->id, '85.25', 'Buen trabajo');
        self::assertSame('85.25', $evaluation->calificacion);
        self::assertSame('manual', $evaluation->tipo_calificacion);
        self::assertSame($this->admin->id, $evaluation->calificado_por);
        self::assertSame('Buen trabajo', $evaluation->retroalimentacion);
        $this->assertDatabaseHas('bitacora', ['accion' => 'evaluaciones.registrar', 'registro_id' => $evaluation->id]);
    }

    public function test_identical_retry_does_not_replace_evaluation_or_duplicate_audit(): void
    {
        $this->grant($this->admin, 'evaluaciones.calificar');
        $submission = $this->submission();
        $service = $this->app->make(EvaluacionService::class);
        $first = $service->registrar($this->admin, $this->institucion->id, $submission->id, '90');
        self::assertSame($first->id, $service->registrar($this->admin, $this->institucion->id, $submission->id, '90.00')->id);
        $this->rejects(fn () => $service->registrar($this->admin, $this->institucion->id, $submission->id, '95.00'), ValidationException::class, 'evaluacion');
        $this->assertDatabaseCount('evaluaciones', 1);
        self::assertSame(1, Bitacora::where('accion', 'evaluaciones.registrar')->count());
    }

    public function test_out_of_range_or_malformed_grades_have_no_side_effects(): void
    {
        $this->grant($this->admin, 'evaluaciones.calificar');
        $submission = $this->submission();
        foreach (['100.01', '-1', '2.001', 'NaN'] as $score) {
            $this->rejects(fn () => $this->app->make(EvaluacionService::class)->registrar($this->admin, $this->institucion->id, $submission->id, $score), ValidationException::class, 'calificacion');
        }
        $this->assertDatabaseCount('evaluaciones', 0);
    }

    public function test_participant_cannot_grade_own_submission(): void
    {
        $submission = $this->submission();
        $this->rejects(fn () => $this->app->make(EvaluacionService::class)->registrar($this->participante->usuario, $this->institucion->id, $submission->id, '100'), AuthorizationException::class);
    }

    public function test_instructor_with_permission_is_limited_to_assigned_groups(): void
    {
        $instructorUser = $this->userWithRole('instructor');
        $instructor = $this->record(Instructor::class, ['user_id' => $instructorUser->id, 'especialidad' => 'Programación']);
        $this->grant($instructorUser, 'evaluaciones.calificar', role: 'instructor');
        $submission = $this->submission();
        $service = $this->app->make(EvaluacionService::class);
        $this->rejects(fn () => $service->registrar($instructorUser, $this->institucion->id, $submission->id, '80'), AuthorizationException::class);
        $this->record(GrupoInstructor::class, ['grupo_id' => $this->grupo->id, 'instructor_id' => $instructor->id]);
        self::assertSame('80.00', $service->registrar($instructorUser, $this->institucion->id, $submission->id, '80')->calificacion);
    }

    public function test_submission_from_another_institution_cannot_be_graded(): void
    {
        $this->grant($this->admin, 'evaluaciones.calificar');
        $foreignGroup = $this->group($this->otraInstitucion);
        $foreignParticipant = $this->participant($this->otraInstitucion);
        $submission = $this->submission($this->enrollment(participant: $foreignParticipant, group: $foreignGroup), $this->activity(group: $foreignGroup));
        $this->rejects(fn () => $this->app->make(EvaluacionService::class)->registrar($this->admin, $this->institucion->id, $submission->id, '80'), ModelNotFoundException::class);
    }

    public function test_mismatched_module_and_course_are_rejected(): void
    {
        $this->grant($this->admin, 'evaluaciones.calificar');
        $otherGroup = $this->group();
        $module = $this->record(ModuloCurso::class, ['curso_id' => $otherGroup->edicion->curso_id, 'titulo' => 'Otro módulo', 'orden' => 1]);
        $submission = $this->submission(activity: $this->activity(['modulo_id' => $module->id]));
        $this->rejects(fn () => $this->app->make(EvaluacionService::class)->registrar($this->admin, $this->institucion->id, $submission->id, '80'), ValidationException::class, 'actividad');
    }

    public function test_invalid_attempt_and_future_submission_are_rejected(): void
    {
        $this->grant($this->admin, 'evaluaciones.calificar');
        $submission = $this->submission(attributes: ['numero_intento' => 3]);
        $service = $this->app->make(EvaluacionService::class);
        $this->rejects(fn () => $service->registrar($this->admin, $this->institucion->id, $submission->id, '80'), ValidationException::class, 'entrega');
        $submission->forceFill(['numero_intento' => 1, 'fecha_entrega' => now()->addSecond()])->save();
        $this->rejects(fn () => $service->registrar($this->admin, $this->institucion->id, $submission->id, '80'), ValidationException::class, 'entrega');
    }

    public function test_unconfirmed_enrollment_cannot_be_graded(): void
    {
        $this->grant($this->admin, 'evaluaciones.calificar');
        $submission = $this->submission($this->pendingEnrollment());
        $this->rejects(fn () => $this->app->make(EvaluacionService::class)->registrar($this->admin, $this->institucion->id, $submission->id, '80'), ValidationException::class, 'entrega');
    }

    public function test_owner_can_read_exact_breakdown_without_exposing_private_answers(): void
    {
        $this->grant($this->admin, 'evaluaciones.calificar');
        $enrollment = $this->enrollment();
        $submission = $this->submission($enrollment, $this->activity(['puntaje_maximo' => '40.00']));
        $service = $this->app->make(EvaluacionService::class);
        $service->registrar($this->admin, $this->institucion->id, $submission->id, '30.00');
        $breakdown = $service->desglose($this->participante->usuario, $this->institucion->id, $enrollment->id);
        self::assertNull($breakdown['calificacion_final']);
        self::assertSame(['numerador' => 300000, 'denominador' => 4000], $breakdown['actividades'][0]['intentos'][0]['porcentaje_exacto']);
        self::assertStringNotContainsString('respuesta_correcta', json_encode($breakdown));
        self::assertStringNotContainsString('respuestas_json', json_encode($breakdown));
    }

    public function test_other_participant_cannot_read_someone_elses_breakdown(): void
    {
        $enrollment = $this->enrollment();
        $other = $this->participant();
        $this->rejects(fn () => $this->app->make(EvaluacionService::class)->desglose($other->usuario, $this->institucion->id, $enrollment->id), AuthorizationException::class);
    }

    public function test_certificate_breakdown_requires_emission_permission_not_grading_permission(): void
    {
        $this->grant($this->admin, 'constancias.emitir');
        $enrollment = $this->enrollment();
        self::assertSame($enrollment->id, $this->app->make(EvaluacionService::class)->desgloseParaConstancia($this->admin, $this->institucion->id, $enrollment->id)['inscripcion_id']);
        self::assertFalse($this->admin->permisosEnInstitucion($this->institucion->id)->where('clave', 'evaluaciones.calificar')->exists());
    }

    public function test_missing_academic_policy_is_reported_without_inventing_final_grade(): void
    {
        $enrollment = $this->enrollment();
        $this->app->instance(ReglasOperacion::class, new ReglasOperacion);
        $breakdown = $this->app->make(EvaluacionService::class)->desglose($this->participante->usuario, $this->institucion->id, $enrollment->id);
        self::assertNull($breakdown['calificacion_final']);
        self::assertSame([], $breakdown['actividades']);
        self::assertStringContainsString('actividad.evaluable', implode(' ', $breakdown['pendientes']));
        $this->assertDatabaseCount('evaluaciones', 0);
    }
}
