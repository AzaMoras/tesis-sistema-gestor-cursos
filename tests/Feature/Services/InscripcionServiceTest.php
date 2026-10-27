<?php

namespace Tests\Feature\Services;

use App\Models\Bitacora;
use App\Models\Inscripcion;
use App\Models\ParticipanteRequisito;
use App\Models\Requisito;
use App\Models\CursoRequisito;
use App\Services\InscripcionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Tests\Support\ServiceTestCase;

class InscripcionServiceTest extends ServiceTestCase
{
    private function enroll(): Inscripcion
    {
        return $this->app->make(InscripcionService::class)->inscribir($this->participante->usuario, $this->institucion->id, $this->participante->id, $this->grupo->id);
    }

    public function test_free_enrollment_is_confirmed_and_audited(): void
    {
        $enrollment = $this->enroll();
        self::assertSame('confirmada', $enrollment->estado);
        self::assertSame('0.00', $enrollment->precio_acordado);
        self::assertNotNull($enrollment->fecha_inscripcion);
        self::assertNull($enrollment->reserva_vence_at);
        $this->assertDatabaseHas('bitacora', ['accion' => 'inscripciones.crear', 'registro_id' => $enrollment->id]);
    }

    public function test_paid_enrollment_uses_edition_price_and_reserves_a_place(): void
    {
        $this->grupo->edicion->forceFill(['precio' => '123.45'])->save();
        $enrollment = $this->enroll();
        self::assertSame('pendiente_pago', $enrollment->estado);
        self::assertSame('123.45', $enrollment->precio_acordado);
        self::assertNull($enrollment->fecha_inscripcion);
        self::assertTrue($enrollment->reserva_vence_at->eq(now()->addMinutes(30)));
    }

    public function test_duplicate_including_cancelled_enrollment_is_rejected(): void
    {
        $enrollment = $this->enroll();
        $this->rejects(fn () => $this->enroll(), ValidationException::class, 'inscripcion');
        $this->app->make(InscripcionService::class)->cancelar($this->participante->usuario, $this->institucion->id, $enrollment->id);
        $this->rejects(fn () => $this->enroll(), ValidationException::class, 'inscripcion');
        $this->assertDatabaseCount('inscripciones', 1);
    }

    public function test_last_seat_cannot_be_taken_by_two_participants(): void
    {
        $this->grupo->forceFill(['cupo_maximo' => 1])->save();
        $this->enroll();
        $other = $this->participant();
        $this->rejects(fn () => $this->app->make(InscripcionService::class)->inscribir($other->usuario, $this->institucion->id, $other->id, $this->grupo->id), ValidationException::class, 'cupo');
        $this->assertDatabaseCount('inscripciones', 1);
    }

    public function test_live_reservation_occupies_capacity_but_expired_reservation_does_not(): void
    {
        $this->grupo->forceFill(['cupo_maximo' => 1])->save();
        $reservation = $this->pendingEnrollment();
        $other = $this->participant();
        $service = $this->app->make(InscripcionService::class);
        $this->rejects(fn () => $service->inscribir($other->usuario, $this->institucion->id, $other->id, $this->grupo->id), ValidationException::class, 'cupo');
        $reservation->forceFill(['reserva_vence_at' => now()->subSecond()])->save();
        self::assertSame('pendiente_pago', $service->inscribir($other->usuario, $this->institucion->id, $other->id, $this->grupo->id)->estado);
    }

    public function test_zero_and_undefined_capacity_do_not_silently_mean_unlimited(): void
    {
        foreach ([0, null] as $capacity) {
            $this->grupo->forceFill(['cupo_maximo' => $capacity])->save();
            $this->rejects(fn () => $this->enroll(), ValidationException::class, 'cupo');
        }
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_other_participant_cannot_enroll_someone_else(): void
    {
        $other = $this->participant();
        $this->rejects(fn () => $this->app->make(InscripcionService::class)->inscribir($other->usuario, $this->institucion->id, $this->participante->id, $this->grupo->id), AuthorizationException::class);
        $this->assertDatabaseCount('inscripciones', 0);
    }

    public function test_authorized_staff_can_enroll_a_participant(): void
    {
        $this->grant($this->admin, 'inscripciones.gestionar');
        $enrollment = $this->app->make(InscripcionService::class)->inscribir($this->admin, $this->institucion->id, $this->participante->id, $this->grupo->id);
        self::assertSame($this->participante->id, $enrollment->participante_id);
    }

    public function test_group_from_another_institution_is_not_accessible(): void
    {
        $foreign = $this->group($this->otraInstitucion);
        $this->rejects(fn () => $this->app->make(InscripcionService::class)->inscribir($this->participante->usuario, $this->institucion->id, $this->participante->id, $foreign->id), ModelNotFoundException::class);
    }

    public function test_draft_group_and_closed_registration_are_rejected(): void
    {
        $this->grupo->forceFill(['estado' => 'borrador'])->save();
        $this->rejects(fn () => $this->enroll(), ValidationException::class, 'grupo');
        $this->grupo->forceFill(['estado' => 'abierto'])->save();
        $this->grupo->edicion->forceFill(['cierre_inscripciones_at' => now()])->save();
        $this->rejects(fn () => $this->enroll(), ValidationException::class, 'edicion');
    }

    public function test_required_document_must_be_validated_with_reviewer_date_and_evidence(): void
    {
        $requirement = $this->record(Requisito::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'Identificación', 'requiere_documento' => true]);
        $this->record(CursoRequisito::class, ['curso_id' => $this->grupo->edicion->curso_id, 'requisito_id' => $requirement->id, 'obligatorio' => true]);
        $this->rejects(fn () => $this->enroll(), ValidationException::class, 'requisitos');
        $validation = $this->record(ParticipanteRequisito::class, ['participante_id' => $this->participante->id, 'requisito_id' => $requirement->id, 'estado' => 'validado', 'revisado_por' => $this->admin->id, 'fecha_validacion' => now()]);
        $this->rejects(fn () => $this->enroll(), ValidationException::class, 'requisitos');
        $validation->ruta_evidencia = 'fixtures/identificacion.pdf';
        $validation->save();
        self::assertSame('confirmada', $this->enroll()->estado);
    }

    public function test_unverified_or_insufficient_payments_never_confirm_enrollment(): void
    {
        $this->grant($this->admin, 'pagos.verificar');
        $enrollment = $this->pendingEnrollment();
        $payment = $this->payment($enrollment);
        $service = $this->app->make(InscripcionService::class);
        $this->rejects(fn () => $service->confirmarConPagosVerificados($this->admin, $this->institucion->id, $enrollment->id), ValidationException::class, 'pago');
        $payment->forceFill(['estado' => 'verificado', 'verificado_por' => $this->admin->id, 'fecha_pago' => now(), 'monto' => '99.99'])->save();
        $this->rejects(fn () => $service->confirmarConPagosVerificados($this->admin, $this->institucion->id, $enrollment->id), ValidationException::class, 'pago');
        self::assertSame('pendiente_pago', $enrollment->fresh()->estado);
    }

    public function test_verified_funds_confirm_once_and_clear_reservation(): void
    {
        $this->grant($this->admin, 'pagos.verificar');
        $enrollment = $this->pendingEnrollment();
        $this->payment($enrollment, ['estado' => 'verificado', 'verificado_por' => $this->admin->id, 'fecha_pago' => now()]);
        $service = $this->app->make(InscripcionService::class);
        $confirmed = $service->confirmarConPagosVerificados($this->admin, $this->institucion->id, $enrollment->id);
        self::assertSame('confirmada', $confirmed->estado);
        self::assertNull($confirmed->reserva_vence_at);
        $service->confirmarConPagosVerificados($this->admin, $this->institucion->id, $enrollment->id);
        self::assertSame(1, Bitacora::where('accion', 'inscripciones.confirmar')->count());
    }

    public function test_expired_paid_reservation_is_not_automatically_recovered(): void
    {
        $this->grant($this->admin, 'pagos.verificar');
        $enrollment = $this->pendingEnrollment();
        $enrollment->forceFill(['reserva_vence_at' => now()])->save();
        $this->payment($enrollment, ['estado' => 'verificado', 'verificado_por' => $this->admin->id, 'fecha_pago' => now()]);
        $this->rejects(fn () => $this->app->make(InscripcionService::class)->confirmarConPagosVerificados($this->admin, $this->institucion->id, $enrollment->id), ValidationException::class, 'inscripcion');
        self::assertSame('pendiente_pago', $enrollment->fresh()->estado);
    }

    public function test_cancellation_is_idempotent_and_blocks_academic_history(): void
    {
        $enrollment = $this->enroll();
        $service = $this->app->make(InscripcionService::class);
        $cancelled = $service->cancelar($this->participante->usuario, $this->institucion->id, $enrollment->id);
        self::assertSame('cancelada', $cancelled->estado);
        self::assertNotNull($cancelled->fecha_cancelacion);
        $service->cancelar($this->participante->usuario, $this->institucion->id, $enrollment->id);
        self::assertSame(1, Bitacora::where('accion', 'inscripciones.cancelar')->count());
        $other = $this->participant();
        $withHistory = $this->enrollment(participant: $other);
        $this->submission($withHistory);
        $this->rejects(fn () => $service->cancelar($other->usuario, $this->institucion->id, $withHistory->id), ValidationException::class, 'inscripcion');
    }
}
