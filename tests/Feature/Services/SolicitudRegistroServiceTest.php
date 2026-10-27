<?php

namespace Tests\Feature\Services;

use App\Models\Bitacora;
use App\Models\DocumentoSolicitud;
use App\Models\SolicitudRegistro;
use App\Models\User;
use App\Services\SolicitudRegistroService;
use App\Services\Soporte\ReglasOperacion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Tests\Support\ServiceTestCase;

class SolicitudRegistroServiceTest extends ServiceTestCase
{
    private function createRequest(array $attributes = []): SolicitudRegistro
    {
        return $this->app->make(SolicitudRegistroService::class)->crear($this->institucion->id, $attributes + [
            'nombre' => 'Ana', 'apellidos' => 'López', 'correo' => 'ana@example.test',
        ]);
    }

    public function test_public_creation_ignores_privileges_and_records_anonymous_audit(): void
    {
        $users = User::count();
        $request = $this->createRequest(['estado' => 'aprobada', 'rol' => 'administrador', 'revisado_por' => $this->admin->id]);
        self::assertSame('pendiente', $request->estado);
        self::assertNull($request->revisado_por);
        self::assertNull($request->usuario_vinculado_id);
        self::assertSame($users, User::count());
        $this->assertDatabaseHas('bitacora', ['accion' => 'solicitudes.crear', 'registro_id' => $request->id, 'usuario_id' => null]);
    }

    public function test_invalid_contact_data_has_no_side_effects(): void
    {
        $this->rejects(fn () => $this->createRequest(['correo' => 'no-es-correo']), ValidationException::class, 'correo');
        $this->assertDatabaseCount('solicitudes_registro', 0);
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_duplicate_pending_email_is_normalized_and_rejected(): void
    {
        $this->createRequest();
        $this->rejects(fn () => $this->createRequest(['correo' => 'ANA@EXAMPLE.TEST']), ValidationException::class, 'correo');
        $this->assertDatabaseCount('solicitudes_registro', 1);
        $this->assertDatabaseCount('bitacora', 1);
    }

    public function test_approval_is_atomic_idempotent_and_only_creates_pending_participant(): void
    {
        $this->grant($this->admin, 'solicitudes.revisar');
        $request = $this->createRequest();
        $service = $this->app->make(SolicitudRegistroService::class);
        $users = User::count();
        $approved = $service->aprobar($this->admin, $this->institucion->id, $request->id);
        $user = User::findOrFail($approved->usuario_vinculado_id);
        self::assertSame('aprobada', $approved->estado);
        self::assertSame('pendiente', $user->estado_cuenta);
        self::assertSame($this->admin->id, $approved->revisado_por);
        self::assertNotNull($user->participante);
        self::assertSame(['participante'], $user->rolesEnInstitucion($this->institucion->id)->pluck('roles.clave')->all());
        self::assertSame('bcrypt', Hash::info($user->password)['algoName']);
        self::assertArrayNotHasKey('password', $user->toArray());
        self::assertSame($approved->id, $service->aprobar($this->admin, $this->institucion->id, $request->id)->id);
        self::assertSame($users + 1, User::count());
        self::assertSame(1, Bitacora::where('accion', 'solicitudes.aprobar')->count());
    }

    public function test_administrator_without_permission_cannot_approve(): void
    {
        $request = $this->createRequest();
        $this->rejects(fn () => $this->app->make(SolicitudRegistroService::class)->aprobar($this->admin, $this->institucion->id, $request->id), AuthorizationException::class);
        self::assertSame('pendiente', $request->fresh()->estado);
    }

    public function test_permission_alone_does_not_allow_coordinator_to_provision_accounts(): void
    {
        $coordinator = $this->userWithRole('coordinador');
        $this->grant($coordinator, 'solicitudes.revisar', role: 'coordinador');
        $request = $this->createRequest();
        $this->rejects(fn () => $this->app->make(SolicitudRegistroService::class)->aprobar($coordinator, $this->institucion->id, $request->id), AuthorizationException::class);
        self::assertNull($request->fresh()->usuario_vinculado_id);
    }

    public function test_existing_email_is_not_linked_without_identity_verification(): void
    {
        $this->grant($this->admin, 'solicitudes.revisar');
        $request = $this->createRequest(['correo' => $this->participante->usuario->email]);
        $users = User::count();
        $this->rejects(fn () => $this->app->make(SolicitudRegistroService::class)->aprobar($this->admin, $this->institucion->id, $request->id), ValidationException::class, 'correo');
        self::assertSame($users, User::count());
        self::assertNull($request->fresh()->usuario_vinculado_id);
    }

    public function test_pending_documents_block_approval(): void
    {
        $this->grant($this->admin, 'solicitudes.revisar');
        $request = $this->createRequest();
        $document = $this->record(DocumentoSolicitud::class, [
            'solicitud_registro_id' => $request->id, 'tipo_documento' => 'identificacion', 'ruta_archivo' => 'fixtures/documento.pdf',
            'nombre_original' => 'documento.pdf', 'tipo_mime' => 'application/pdf', 'tamano_bytes' => 100, 'estado_revision' => 'pendiente',
        ]);
        $service = $this->app->make(SolicitudRegistroService::class);
        $this->rejects(fn () => $service->aprobar($this->admin, $this->institucion->id, $request->id), ValidationException::class, 'documentos');
        $document->forceFill(['estado_revision' => 'aprobado'])->save();
        self::assertSame('aprobada', $service->aprobar($this->admin, $this->institucion->id, $request->id)->estado);
    }

    public function test_rejection_records_reviewer_and_cannot_be_reapproved(): void
    {
        $this->grant($this->admin, 'solicitudes.revisar');
        $request = $this->createRequest();
        $service = $this->app->make(SolicitudRegistroService::class);
        $rejected = $service->rechazar($this->admin, $this->institucion->id, $request->id, 'Documentación incompleta');
        self::assertSame('rechazada', $rejected->estado);
        self::assertSame('Documentación incompleta', $rejected->motivo_revision);
        self::assertNotNull($rejected->fecha_revision);
        self::assertSame($rejected->id, $service->rechazar($this->admin, $this->institucion->id, $request->id, 'Documentación incompleta')->id);
        $this->rejects(fn () => $service->aprobar($this->admin, $this->institucion->id, $request->id), ValidationException::class, 'solicitud');
    }

    public function test_consultation_is_scoped_to_institution(): void
    {
        $this->grant($this->admin, 'solicitudes.revisar');
        $this->assign($this->admin, 'administrador', $this->otraInstitucion);
        $request = $this->createRequest();
        $service = $this->app->make(SolicitudRegistroService::class);
        self::assertSame($request->id, $service->consultar($this->admin, $this->institucion->id, $request->id)->id);
        $this->rejects(fn () => $service->consultar($this->admin, $this->otraInstitucion->id, $request->id), ModelNotFoundException::class);
    }

    public function test_missing_policy_blocks_approval_without_creating_accounts(): void
    {
        $this->grant($this->admin, 'solicitudes.revisar');
        $request = $this->createRequest();
        $this->app->instance(ReglasOperacion::class, new ReglasOperacion);
        $users = User::count();
        $this->rejects(fn () => $this->app->make(SolicitudRegistroService::class)->aprobar($this->admin, $this->institucion->id, $request->id), LogicException::class);
        self::assertSame($users, User::count());
        self::assertSame('pendiente', $request->fresh()->estado);
    }

    public function test_audit_failure_rolls_back_account_profile_role_and_approval(): void
    {
        $this->grant($this->admin, 'solicitudes.revisar');
        $request = $this->createRequest();
        $users = User::count();
        $this->app['events']->listen('eloquent.creating: '.Bitacora::class, fn () => throw new RuntimeException('Auditoría no disponible'));
        $this->rejects(fn () => $this->app->make(SolicitudRegistroService::class)->aprobar($this->admin, $this->institucion->id, $request->id), RuntimeException::class);
        self::assertSame($users, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'ana@example.test']);
        self::assertSame('pendiente', $request->fresh()->estado);
        $this->assertDatabaseCount('participantes', 1);
    }
}
