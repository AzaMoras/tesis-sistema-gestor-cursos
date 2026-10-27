<?php

namespace Tests\Feature\Services;

use App\Models\Asistencia;
use App\Models\Sesion;
use App\Services\ConstanciaService;
use App\Services\EvaluacionService;
use App\Services\Soporte\ReglasOperacion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\Support\ServiceTestCase;

class ConstanciaServiceTest extends ServiceTestCase
{
    public function test_incomplete_academic_rules_never_issue_fictitious_certificate(): void
    {
        $this->grant($this->admin, 'constancias.emitir');
        $this->grant($this->admin, 'evaluaciones.calificar');
        $enrollment = $this->enrollment();
        $submission = $this->submission($enrollment);
        $this->app->make(EvaluacionService::class)->registrar($this->admin, $this->institucion->id, $submission->id, '100');
        $result = $this->app->make(ConstanciaService::class)->comprobarElegibilidad($this->admin, $this->institucion->id, $enrollment->id);
        self::assertFalse($result['elegible']);
        self::assertTrue($result['requiere_definiciones']);
        self::assertNull($result['calificacion_final']);
        self::assertNull($result['porcentaje_asistencia']);
        self::assertSame('70.00', $result['calificacion_minima']);
        self::assertStringContainsString('PDF', implode(' ', $result['motivos']));
        $this->assertDatabaseCount('constancias', 0);
    }

    public function test_unconfirmed_enrollment_and_missing_grades_are_reported(): void
    {
        $this->grant($this->admin, 'constancias.emitir');
        $enrollment = $this->pendingEnrollment();
        $this->activity();
        $result = $this->app->make(ConstanciaService::class)->comprobarElegibilidad($this->admin, $this->institucion->id, $enrollment->id);
        self::assertContains('La inscripción no está confirmada.', $result['motivos']);
        self::assertContains('Hay 1 actividades sin una evaluación registrada.', $result['motivos']);
        self::assertFalse($result['elegible']);
    }

    public function test_missing_policy_is_returned_as_diagnostic_not_fabricated_eligibility(): void
    {
        $this->grant($this->admin, 'constancias.emitir');
        $enrollment = $this->enrollment();
        $this->app->instance(ReglasOperacion::class, new ReglasOperacion);
        $result = $this->app->make(ConstanciaService::class)->comprobarElegibilidad($this->admin, $this->institucion->id, $enrollment->id);
        self::assertFalse($result['elegible']);
        self::assertStringContainsString('inscripcion.confirmada', implode(' ', $result['motivos']));
        self::assertStringContainsString('actividad.evaluable', implode(' ', $result['motivos']));
    }

    public function test_attendance_from_another_group_is_reported_as_inconsistent(): void
    {
        $this->grant($this->admin, 'constancias.emitir');
        $enrollment = $this->enrollment();
        $otherGroup = $this->group();
        $session = $this->record(Sesion::class, ['grupo_id' => $otherGroup->id, 'titulo' => 'Otra sesión', 'inicio_at' => now()->subHours(2), 'fin_at' => now()->subHour()]);
        $this->record(Asistencia::class, ['sesion_id' => $session->id, 'inscripcion_id' => $enrollment->id, 'estado' => 'presente', 'fecha_registro' => now()]);
        $result = $this->app->make(ConstanciaService::class)->comprobarElegibilidad($this->admin, $this->institucion->id, $enrollment->id);
        self::assertContains('El expediente tiene asistencias de otro grupo o con fechas inconsistentes.', $result['motivos']);
        self::assertFalse($result['elegible']);
    }

    public function test_participant_cannot_run_staff_eligibility_operation_without_permission(): void
    {
        $enrollment = $this->enrollment();
        $this->rejects(fn () => $this->app->make(ConstanciaService::class)->comprobarElegibilidad($this->participante->usuario, $this->institucion->id, $enrollment->id), AuthorizationException::class);
    }

    public function test_owner_can_read_existing_certificate_and_absence_returns_null(): void
    {
        $enrollment = $this->enrollment();
        $service = $this->app->make(ConstanciaService::class);
        self::assertNull($service->obtenerExistente($this->participante->usuario, $this->institucion->id, $enrollment->id));
        $certificate = $this->certificate($enrollment);
        self::assertSame($certificate->id, $service->obtenerExistente($this->participante->usuario, $this->institucion->id, $enrollment->id)->id);
        $this->assertDatabaseCount('constancias', 1);
    }

    public function test_non_owner_cannot_read_someone_elses_certificate(): void
    {
        $enrollment = $this->enrollment();
        $this->certificate($enrollment);
        $other = $this->participant();
        $this->rejects(fn () => $this->app->make(ConstanciaService::class)->obtenerExistente($other->usuario, $this->institucion->id, $enrollment->id), AuthorizationException::class);
    }

    public function test_even_authorized_staff_cannot_use_the_wrong_institution(): void
    {
        $this->grant($this->admin, 'constancias.emitir');
        $this->assign($this->admin, 'administrador', $this->otraInstitucion);
        $enrollment = $this->enrollment();
        $this->certificate($enrollment);
        $this->rejects(fn () => $this->app->make(ConstanciaService::class)->obtenerExistente($this->admin, $this->otraInstitucion->id, $enrollment->id), ModelNotFoundException::class);
    }
}
