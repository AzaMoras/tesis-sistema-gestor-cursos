<?php

namespace Tests\Feature\Services;

use App\Models\CategoriaCurso;
use App\Models\User;
use App\Services\Soporte\AutorizacionInstitucional;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\ServiceTestCase;

class AutorizacionInstitucionalTest extends ServiceTestCase
{
    public function test_unsaved_user_cannot_authorize_a_service(): void
    {
        $this->rejects(fn () => $this->app->make(AutorizacionInstitucional::class)->usuario(new User, $this->institucion->id), AuthorizationException::class);
    }

    public function test_account_is_reloaded_and_forged_active_attribute_does_not_grant_access(): void
    {
        User::query()->whereKey($this->admin->id)->update(['estado_cuenta' => 'pendiente']);
        self::assertSame('activa', $this->admin->estado_cuenta);
        $this->rejects(fn () => $this->app->make(AutorizacionInstitucional::class)->usuario($this->admin, $this->institucion->id), AuthorizationException::class);
    }

    public function test_soft_deleted_user_cannot_authorize_even_with_stale_model(): void
    {
        $stale = clone $this->admin;
        $this->admin->delete();
        $this->rejects(fn () => $this->app->make(AutorizacionInstitucional::class)->usuario($stale, $this->institucion->id), AuthorizationException::class);
    }

    public function test_inactive_or_soft_deleted_institution_is_not_available(): void
    {
        $access = $this->app->make(AutorizacionInstitucional::class);
        $this->institucion->forceFill(['estado' => 'inactiva'])->save();
        $this->rejects(fn () => $access->institucion($this->institucion->id), ModelNotFoundException::class);
        $this->institucion->forceFill(['estado' => 'activa'])->save();
        $this->institucion->delete();
        $this->rejects(fn () => $access->institucion($this->institucion->id), ModelNotFoundException::class);
    }

    public function test_permission_in_one_institution_is_not_inherited_in_another(): void
    {
        $user = $this->userWithRole('participante');
        $this->grant($user, 'pagos.verificar', $this->otraInstitucion, 'coordinador');
        $access = $this->app->make(AutorizacionInstitucional::class);
        self::assertSame($user->id, $access->usuario($user, $this->otraInstitucion->id, 'pagos.verificar')->id);
        $this->rejects(fn () => $access->usuario($user, $this->institucion->id, 'pagos.verificar'), AuthorizationException::class);
    }

    public function test_administrator_has_no_implicit_permission_bypass(): void
    {
        $this->rejects(fn () => $this->app->make(AutorizacionInstitucional::class)->usuario($this->admin, $this->institucion->id, 'pagos.verificar'), AuthorizationException::class);
        $this->grant($this->admin, 'pagos.verificar');
        self::assertSame($this->admin->id, $this->app->make(AutorizacionInstitucional::class)->usuario($this->admin, $this->institucion->id, 'pagos.verificar')->id);
    }

    public function test_participant_profile_requires_participant_role_in_selected_institution(): void
    {
        $this->rejects(fn () => $this->app->make(AutorizacionInstitucional::class)->participante($this->otraInstitucion->id, $this->participante->id), AuthorizationException::class);
    }

    public function test_group_category_cannot_belong_to_another_institution(): void
    {
        $category = $this->record(CategoriaCurso::class, ['institucion_id' => $this->otraInstitucion->id, 'nombre' => 'Ajena', 'slug' => (string) Str::uuid()]);
        $this->grupo->edicion->curso->forceFill(['categoria_id' => $category->id])->save();
        $this->rejects(fn () => $this->app->make(AutorizacionInstitucional::class)->grupo($this->institucion->id, $this->grupo->id), AuthorizationException::class);
    }

    public function test_group_and_enrollment_are_scoped_and_locked_within_a_transaction(): void
    {
        $enrollment = $this->enrollment();
        $access = $this->app->make(AutorizacionInstitucional::class);
        DB::transaction(function () use ($access, $enrollment): void {
            $resolved = $access->inscripcion($this->institucion->id, $enrollment->id, true);
            self::assertSame($this->grupo->id, $resolved->grupo->id);
            self::assertSame($this->institucion->id, $resolved->grupo->edicion->curso->institucion_id);
            self::assertSame($this->participante->id, $resolved->participante->id);
        });
        $this->rejects(fn () => $access->inscripcion($this->otraInstitucion->id, $enrollment->id), ModelNotFoundException::class);
    }

    public function test_locking_without_transaction_is_refused(): void
    {
        // Solo termina la transacción de fixtures de SQLite :memory: verificada.
        DB::rollBack();
        $this->rejects(fn () => $this->app->make(AutorizacionInstitucional::class)->grupo($this->institucion->id, $this->grupo->id, true), LogicException::class);
    }
}
