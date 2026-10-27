<?php

namespace Tests\Feature\Services;

use App\Models\Bitacora;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Services\InscripcionService;
use App\Services\PagoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\ServiceTestCase;

class PagoServiceTest extends ServiceTestCase
{
    private function register(Inscripcion $enrollment, ?string $reference = null, array $data = []): Pago
    {
        return $this->app->make(PagoService::class)->registrar($this->participante->usuario, $this->institucion->id, $enrollment->id,
            $reference ?? (string) Str::uuid(), $data + ['monto' => '100.00', 'metodo' => 'transferencia']);
    }

    public function test_registration_cannot_confirm_payment_or_accept_privileged_fields(): void
    {
        $enrollment = $this->pendingEnrollment();
        $payment = $this->register($enrollment, data: ['estado' => 'verificado', 'verificado_por' => $this->admin->id, 'moneda' => 'USD', 'fecha_pago' => now()]);
        self::assertSame('pendiente', $payment->estado);
        self::assertSame('MXN', $payment->moneda);
        self::assertNull($payment->verificado_por);
        self::assertNull($payment->fecha_pago);
        self::assertSame('pendiente_pago', $enrollment->fresh()->estado);
    }

    public function test_reference_retries_return_one_payment_and_one_audit(): void
    {
        $enrollment = $this->pendingEnrollment();
        $reference = (string) Str::uuid();
        $first = $this->register($enrollment, $reference);
        self::assertSame($first->id, $this->register($enrollment, $reference)->id);
        $this->assertDatabaseCount('pagos', 1);
        self::assertSame(1, Bitacora::where('accion', 'pagos.registrar')->count());
        $this->rejects(fn () => $this->register($enrollment, $reference, ['monto' => '90.00']), ValidationException::class, 'referencia_interna');
    }

    public function test_duplicate_external_reference_is_rejected_without_partial_writes(): void
    {
        $enrollment = $this->pendingEnrollment();
        $this->register($enrollment, data: ['referencia_externa' => 'banco-123']);
        $this->rejects(fn () => $this->register($enrollment, data: ['referencia_externa' => 'banco-123']), ValidationException::class, 'referencia');
        $this->assertDatabaseCount('pagos', 1);
        self::assertSame(1, Bitacora::where('accion', 'pagos.registrar')->count());
    }

    public function test_invalid_amounts_float_and_invalid_reference_are_rejected(): void
    {
        $enrollment = $this->pendingEnrollment();
        foreach (['0.00', '100.01', '-1', '1.001', 10.5] as $amount) {
            $this->rejects(fn () => $this->register($enrollment, data: ['monto' => $amount]), ValidationException::class, 'monto');
        }
        $this->rejects(fn () => $this->register($enrollment, 'referencia-no-uuid'), ValidationException::class, 'referencia_interna');
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_new_payment_requires_live_reservation(): void
    {
        $enrollment = $this->pendingEnrollment();
        $enrollment->forceFill(['reserva_vence_at' => now()])->save();
        $this->rejects(fn () => $this->register($enrollment), ValidationException::class, 'inscripcion');
        $this->assertDatabaseCount('pagos', 0);
    }

    public function test_other_participant_cannot_register_payment_for_someone_else(): void
    {
        $enrollment = $this->pendingEnrollment();
        $other = $this->participant();
        $this->rejects(fn () => $this->app->make(PagoService::class)->registrar($other->usuario, $this->institucion->id, $enrollment->id,
            (string) Str::uuid(), ['monto' => '100.00', 'metodo' => 'transferencia']), AuthorizationException::class);
    }

    public function test_participant_cannot_verify_own_payment_without_permission(): void
    {
        $payment = $this->register($this->pendingEnrollment());
        $this->rejects(fn () => $this->app->make(PagoService::class)->verificarManual($this->participante->usuario, $this->institucion->id,
            $payment->id, now(), 'El formulario dice confirmado'), AuthorizationException::class);
        self::assertSame('pendiente', $payment->fresh()->estado);
    }

    public function test_manual_verification_is_audited_idempotent_and_does_not_confirm_enrollment(): void
    {
        $this->grant($this->admin, 'pagos.verificar');
        $enrollment = $this->pendingEnrollment();
        $payment = $this->register($enrollment);
        $service = $this->app->make(PagoService::class);
        $verified = $service->verificarManual($this->admin, $this->institucion->id, $payment->id, now()->subHour(), 'Cotejado por personal autorizado');
        self::assertSame('verificado', $verified->estado);
        self::assertSame($this->admin->id, $verified->verificado_por);
        self::assertTrue($verified->fecha_pago->eq(now()->subHour()));
        self::assertSame('pendiente_pago', $enrollment->fresh()->estado);
        $service->verificarManual($this->admin, $this->institucion->id, $payment->id, now()->subHour(), 'Cotejado por personal autorizado');
        $audit = Bitacora::where('accion', 'pagos.verificar_manual')->sole();
        self::assertSame('Cotejado por personal autorizado', $audit->detalles_json['evidencia_revision']);
    }

    public function test_verification_requires_real_date_and_review_evidence(): void
    {
        $this->grant($this->admin, 'pagos.verificar');
        $payment = $this->register($this->pendingEnrollment());
        $service = $this->app->make(PagoService::class);
        $this->rejects(fn () => $service->verificarManual($this->admin, $this->institucion->id, $payment->id, now()->addSecond(), 'Revisión'), ValidationException::class, 'fecha_pago');
        $this->rejects(fn () => $service->verificarManual($this->admin, $this->institucion->id, $payment->id, now(), ''), ValidationException::class, 'evidencia_revision');
        self::assertSame('pendiente', $payment->fresh()->estado);
    }

    public function test_mismatched_currency_cannot_be_verified(): void
    {
        $this->grant($this->admin, 'pagos.verificar');
        $payment = $this->payment($this->pendingEnrollment(), ['moneda' => 'USD']);
        $this->rejects(fn () => $this->app->make(PagoService::class)->verificarManual($this->admin, $this->institucion->id, $payment->id, now(), 'Revisión'), ValidationException::class, 'pago');
    }

    public function test_partial_payments_cannot_be_verified_as_an_overpayment(): void
    {
        $this->grant($this->admin, 'pagos.verificar');
        $enrollment = $this->pendingEnrollment();
        $first = $this->register($enrollment, data: ['monto' => '60.00']);
        $second = $this->register($enrollment, data: ['monto' => '60.00']);
        $service = $this->app->make(PagoService::class);
        $service->verificarManual($this->admin, $this->institucion->id, $first->id, now(), 'Primer abono revisado');
        $this->rejects(fn () => $service->verificarManual($this->admin, $this->institucion->id, $second->id, now(), 'Segundo abono revisado'), ValidationException::class, 'monto');
        self::assertSame('pendiente', $second->fresh()->estado);
    }

    public function test_real_late_payment_does_not_recover_expired_place(): void
    {
        $this->grant($this->admin, 'pagos.verificar');
        $enrollment = $this->pendingEnrollment();
        $payment = $this->register($enrollment);
        $enrollment->forceFill(['reserva_vence_at' => now()->subMinute()])->save();
        $verified = $this->app->make(PagoService::class)->verificarManual($this->admin, $this->institucion->id, $payment->id, now(), 'Transferencia tardía cotejada');
        self::assertSame('verificado', $verified->estado);
        $this->rejects(fn () => $this->app->make(InscripcionService::class)->confirmarConPagosVerificados($this->admin, $this->institucion->id, $enrollment->id), ValidationException::class, 'inscripcion');
        self::assertSame('pendiente_pago', $enrollment->fresh()->estado);
    }

    public function test_rejection_is_idempotent_and_does_not_simulate_refund(): void
    {
        $this->grant($this->admin, 'pagos.verificar');
        $payment = $this->register($this->pendingEnrollment());
        $service = $this->app->make(PagoService::class);
        self::assertSame('rechazado', $service->rechazar($this->admin, $this->institucion->id, $payment->id, 'Referencia inválida')->estado);
        $service->rechazar($this->admin, $this->institucion->id, $payment->id, 'Referencia inválida');
        self::assertSame(1, Bitacora::where('accion', 'pagos.rechazar')->count());
        self::assertSame('0.00', $payment->fresh()->monto_reembolsado);
        $this->rejects(fn () => $service->verificarManual($this->admin, $this->institucion->id, $payment->id, now(), 'Revisión'), ValidationException::class, 'pago');
    }

    public function test_payment_cannot_be_reviewed_using_another_institution(): void
    {
        $this->grant($this->admin, 'pagos.verificar');
        $this->assign($this->admin, 'administrador', $this->otraInstitucion);
        $payment = $this->register($this->pendingEnrollment());
        $this->rejects(fn () => $this->app->make(PagoService::class)->rechazar($this->admin, $this->otraInstitucion->id, $payment->id, 'Motivo'), ModelNotFoundException::class);
        self::assertSame('pendiente', $payment->fresh()->estado);
    }
}
