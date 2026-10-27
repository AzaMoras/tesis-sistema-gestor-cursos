<?php

namespace Tests\Feature\Services;

use App\Models\Bitacora;
use App\Models\SolicitudRegistro;
use App\Services\Soporte\AuditoriaService;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\Support\ServiceTestCase;

class AuditoriaServiceTest extends ServiceTestCase
{
    public function test_records_actor_institution_entity_and_details_in_same_transaction(): void
    {
        $enrollment = $this->enrollment();
        DB::transaction(fn () => $this->app->make(AuditoriaService::class)->registrar($this->admin, $this->institucion->id, 'operacion.prueba', $enrollment, ['anterior' => 'pendiente_pago']));
        $audit = Bitacora::sole();
        self::assertSame($this->admin->id, $audit->usuario_id);
        self::assertSame($this->institucion->id, $audit->institucion_id);
        self::assertSame('inscripciones', $audit->entidad);
        self::assertSame($enrollment->id, $audit->registro_id);
        self::assertSame(['anterior' => 'pendiente_pago'], $audit->detalles_json);
        self::assertTrue($audit->fecha_accion->eq(now()));
    }

    public function test_public_operation_can_be_audited_without_invented_user(): void
    {
        $enrollment = $this->enrollment();
        DB::transaction(fn () => $this->app->make(AuditoriaService::class)->registrar(null, $this->institucion->id, 'publica.prueba', $enrollment));
        self::assertNull(Bitacora::sole()->usuario_id);
    }

    public function test_unsaved_record_is_not_accepted(): void
    {
        $this->rejects(fn () => $this->app->make(AuditoriaService::class)->registrar($this->admin, $this->institucion->id, 'prueba', new SolicitudRegistro), LogicException::class);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_audit_rolls_back_with_business_transaction(): void
    {
        $enrollment = $this->enrollment();
        $this->rejects(function () use ($enrollment): void {
            DB::transaction(function () use ($enrollment): void {
                $enrollment->estado = 'cancelada';
                $enrollment->save();
                $this->app->make(AuditoriaService::class)->registrar($this->admin, $this->institucion->id, 'prueba.rollback', $enrollment);
                throw new RuntimeException('Falla posterior');
            });
        }, RuntimeException::class);
        self::assertSame('confirmada', $enrollment->fresh()->estado);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_audit_outside_transaction_is_refused(): void
    {
        $enrollment = $this->enrollment();
        DB::rollBack();
        $this->rejects(fn () => $this->app->make(AuditoriaService::class)->registrar($this->admin, $this->institucion->id, 'prueba', $enrollment), LogicException::class);
    }
}
