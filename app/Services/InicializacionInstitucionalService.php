<?php

namespace App\Services;

use App\Models\Institucion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use App\Models\UsuarioRol;
use App\Services\Soporte\AuditoriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InicializacionInstitucionalService
{
    public const SLUG = 'aula-verde-desarrollo-demo';

    public const NOMBRE = 'Institución DEMOSTRATIVA Aula Verde (desarrollo local)';

    public function __construct(private AuditoriaService $auditoria) {}

    /** Solo lo invoca el comando después de comprobar destino, respaldo y consentimiento. */
    public function inicializar(int $usuarioId, string $correoConfirmado, string $nombreConfirmado, string $respaldoSha256): Institucion
    {
        return DB::transaction(function () use ($usuarioId, $correoConfirmado, $nombreConfirmado, $respaldoSha256) {
            $usuario = User::query()->lockForUpdate()->find($usuarioId);
            if (! $usuario || $usuario->estado_cuenta !== 'activa'
                || $usuario->email !== $correoConfirmado || $usuario->name !== $nombreConfirmado) {
                throw ValidationException::withMessages(['usuario' => 'La cuenta ya no coincide con la cuenta activa confirmada.']);
            }
            $institucion = Institucion::withTrashed()->where('slug', self::SLUG)->lockForUpdate()->first();
            if ($institucion && ($institucion->trashed() || $institucion->estado !== 'activa' || $institucion->nombre !== self::NOMBRE)) {
                throw ValidationException::withMessages(['institucion' => 'El identificador de demostración está ocupado por una institución distinta, inactiva o eliminada.']);
            }
            $cambios = [];
            if (! $institucion) {
                $institucion = new Institucion;
                $institucion->forceFill(['slug' => self::SLUG, 'nombre' => self::NOMBRE, 'estado' => 'activa'])->save();
                $cambios[] = 'institucion';
            }
            $rol = Rol::query()->where('clave', 'administrador')->lockForUpdate()->first();
            if (! $rol) {
                $rol = new Rol;
                $rol->forceFill(['clave' => 'administrador', 'nombre' => 'Administrador'])->save();
                $cambios[] = 'rol';
            }
            $permiso = Permiso::query()->where('clave', 'cursos.gestionar')->lockForUpdate()->first();
            if (! $permiso) {
                $permiso = new Permiso;
                $permiso->forceFill(['clave' => 'cursos.gestionar', 'descripcion' => 'Gestionar oferta institucional'])->save();
                $cambios[] = 'permiso';
            }
            if (! DB::table('rol_permiso')->where('rol_id', $rol->id)->where('permiso_id', $permiso->id)->exists()) {
                DB::table('rol_permiso')->insert(['rol_id' => $rol->id, 'permiso_id' => $permiso->id]);
                $cambios[] = 'rol_permiso';
            }
            $asignacion = UsuarioRol::query()->where('user_id', $usuario->id)->where('rol_id', $rol->id)->where('institucion_id', $institucion->id)->first();
            if (! $asignacion) {
                $asignacion = new UsuarioRol;
                // El operador de consola no se atribuye falsamente a una cuenta web.
                $asignacion->forceFill(['user_id' => $usuario->id, 'rol_id' => $rol->id, 'institucion_id' => $institucion->id, 'asignado_por' => null])->save();
                $cambios[] = 'usuario_rol';
            }
            if ($cambios !== []) {
                $this->auditoria->registrar(null, $institucion->id, 'institucion.inicializar_local', $asignacion, [
                    'origen' => 'artisan', 'usuario_destino' => $usuario->id, 'cambios' => $cambios,
                    'respaldo_sha256' => $respaldoSha256,
                ]);
            }

            return $institucion;
        }, 3);
    }
}
