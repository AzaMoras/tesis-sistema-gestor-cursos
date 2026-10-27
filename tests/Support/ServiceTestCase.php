<?php

namespace Tests\Support;

use App\Models\Actividad;
use App\Models\CategoriaCurso;
use App\Models\Constancia;
use App\Models\Curso;
use App\Models\Edicion;
use App\Models\Entrega;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Institucion;
use App\Models\Pago;
use App\Models\Participante;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\RolPermiso;
use App\Models\User;
use App\Models\UsuarioRol;
use App\Services\Soporte\ReglasOperacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Throwable;

abstract class ServiceTestCase extends TestCase
{
    use RefreshDatabase;

    protected Institucion $institucion;
    protected Institucion $otraInstitucion;
    protected Grupo $grupo;
    protected User $admin;
    protected Participante $participante;
    protected ReglasOperacion $reglas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-27 12:00:00'));
        $this->institucion = $this->institution();
        $this->otraInstitucion = $this->institution();
        $this->grupo = $this->group($this->institucion);
        $this->admin = $this->userWithRole('administrador');
        $this->participante = $this->participant();
        // Convenciones exclusivas del fixture; no configuran reglas de producción.
        $this->reglas = new ReglasOperacion(
            estados: [
                'solicitud.aprobada' => 'aprobada', 'solicitud.rechazada' => 'rechazada',
                'inscripcion.confirmada' => 'confirmada', 'inscripcion.cancelada' => 'cancelada',
                'pago.verificado' => 'verificado', 'pago.rechazado' => 'rechazado',
                'requisito.validado' => 'validado', 'actividad.evaluable' => 'publicada',
                'documento.aprobado' => 'aprobado',
            ],
            apertura: ['curso' => ['publicado'], 'edicion' => ['abierta'], 'grupo' => ['abierto']],
            minutosReserva: 30,
            cupoNuloIlimitado: false,
        );
        $this->app->instance(ReglasOperacion::class, $this->reglas);
    }

    /** Datos de fixture controlados; nunca cambia fillable ni modelos productivos. */
    protected function record(string $class, array $attributes): Model
    {
        $model = new $class;
        $model->forceFill($attributes)->save();

        return $model;
    }

    protected function institution(): Institucion
    {
        return $this->record(Institucion::class, ['nombre' => 'Institución de prueba', 'slug' => (string) Str::uuid(), 'estado' => 'activa']);
    }

    protected function group(?Institucion $institution = null, array $attributes = []): Grupo
    {
        $institution ??= $this->institucion;
        $category = $this->record(CategoriaCurso::class, ['institucion_id' => $institution->id, 'nombre' => 'Categoría', 'slug' => (string) Str::uuid()]);
        $course = $this->record(Curso::class, ['institucion_id' => $institution->id, 'categoria_id' => $category->id, 'titulo' => 'Curso', 'descripcion' => 'Descripción', 'estado' => 'publicado']);
        $edition = $this->record(Edicion::class, [
            'curso_id' => $course->id, 'clave' => (string) Str::uuid(), 'fecha_inicio' => today()->addDay(),
            'fecha_fin' => today()->addMonth(), 'cierre_inscripciones_at' => now()->addDay(), 'estado' => 'abierta', 'precio' => '0.00', 'moneda' => 'MXN',
        ]);

        return $this->record(Grupo::class, $attributes + ['edicion_id' => $edition->id, 'codigo' => (string) Str::uuid(), 'cupo_maximo' => 2, 'estado' => 'abierto']);
    }

    protected function role(string $key): Rol
    {
        return Rol::query()->where('clave', $key)->first() ?? $this->record(Rol::class, ['clave' => $key, 'nombre' => ucfirst($key)]);
    }

    protected function assign(User $user, string $role, ?Institucion $institution = null): void
    {
        $institution ??= $this->institucion;
        $roleModel = $this->role($role);
        if (! UsuarioRol::query()->where('user_id', $user->id)->where('rol_id', $roleModel->id)->where('institucion_id', $institution->id)->exists()) {
            $this->record(UsuarioRol::class, ['user_id' => $user->id, 'rol_id' => $roleModel->id, 'institucion_id' => $institution->id]);
        }
    }

    protected function userWithRole(string $role, ?Institucion $institution = null): User
    {
        $user = User::factory()->create(['estado_cuenta' => 'activa']);
        $this->assign($user, $role, $institution);

        return $user;
    }

    protected function participant(?Institucion $institution = null): Participante
    {
        $user = $this->userWithRole('participante', $institution);

        return $this->record(Participante::class, ['user_id' => $user->id, 'fecha_alta' => today()]);
    }

    protected function grant(User $user, string $key, ?Institucion $institution = null, string $role = 'administrador'): void
    {
        $this->assign($user, $role, $institution);
        $roleModel = $this->role($role);
        $permission = Permiso::query()->where('clave', $key)->first() ?? $this->record(Permiso::class, ['clave' => $key]);
        if (! RolPermiso::query()->where('rol_id', $roleModel->id)->where('permiso_id', $permission->id)->exists()) {
            $this->record(RolPermiso::class, ['rol_id' => $roleModel->id, 'permiso_id' => $permission->id]);
        }
    }

    protected function enrollment(array $attributes = [], ?Participante $participant = null, ?Grupo $group = null): Inscripcion
    {
        $participant ??= $this->participante;
        $group ??= $this->grupo;

        return $this->record(Inscripcion::class, $attributes + [
            'participante_id' => $participant->id, 'grupo_id' => $group->id, 'estado' => 'confirmada',
            'precio_acordado' => $group->edicion->precio, 'fecha_inscripcion' => now()->subDay(),
        ]);
    }

    protected function pendingEnrollment(): Inscripcion
    {
        $this->grupo->edicion->forceFill(['precio' => '100.00'])->save();

        return $this->enrollment(['estado' => 'pendiente_pago', 'fecha_inscripcion' => null, 'reserva_vence_at' => now()->addMinutes(30)]);
    }

    protected function activity(array $attributes = [], ?Grupo $group = null): Actividad
    {
        $group ??= $this->grupo;

        return $this->record(Actividad::class, $attributes + [
            'curso_id' => $group->edicion->curso_id, 'grupo_id' => $group->id, 'titulo' => 'Actividad',
            'tipo' => 'tarea', 'puntaje_maximo' => '100.00', 'ponderacion' => '100.00', 'intentos_maximos' => 2,
            'estado' => 'publicada', 'configuracion_json' => ['respuesta_correcta' => 'privada'],
        ]);
    }

    protected function submission(?Inscripcion $enrollment = null, ?Actividad $activity = null, array $attributes = []): Entrega
    {
        $enrollment ??= $this->enrollment();
        $activity ??= $this->activity();

        return $this->record(Entrega::class, $attributes + [
            'inscripcion_id' => $enrollment->id, 'actividad_id' => $activity->id, 'numero_intento' => 1,
            'fecha_entrega' => now()->subHour(), 'estado' => 'enviada', 'respuestas_json' => ['respuesta' => 'privada'],
        ]);
    }

    protected function payment(Inscripcion $enrollment, array $attributes = []): Pago
    {
        return $this->record(Pago::class, $attributes + [
            'inscripcion_id' => $enrollment->id, 'referencia_interna' => (string) Str::uuid(),
            'monto' => $enrollment->precio_acordado, 'moneda' => 'MXN', 'metodo' => 'transferencia', 'estado' => 'pendiente',
        ]);
    }

    protected function certificate(Inscripcion $enrollment): Constancia
    {
        return $this->record(Constancia::class, [
            'inscripcion_id' => $enrollment->id, 'folio' => (string) Str::uuid(), 'codigo_verificacion' => (string) Str::uuid(),
            'calificacion_final' => '90.00', 'porcentaje_asistencia' => '100.00', 'ruta_pdf' => 'fixtures/constancia.pdf', 'fecha_emision' => now(),
        ]);
    }

    protected function rejects(callable $operation, string $exception, ?string $field = null): Throwable
    {
        try {
            $operation();
        } catch (Throwable $error) {
            self::assertInstanceOf($exception, $error);
            if ($field !== null) {
                self::assertInstanceOf(ValidationException::class, $error);
                self::assertArrayHasKey($field, $error->errors());
            }

            return $error;
        }
        self::fail('Se esperaba '.$exception.' y la operación fue aceptada.');
    }
}
