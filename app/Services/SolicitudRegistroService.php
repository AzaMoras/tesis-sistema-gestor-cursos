<?php

namespace App\Services;

use App\Models\Institucion;
use App\Models\Participante;
use App\Models\Rol;
use App\Models\SolicitudRegistro;
use App\Models\User;
use App\Models\UsuarioRol;
use App\Services\Soporte\AuditoriaService;
use App\Services\Soporte\AutorizacionInstitucional;
use App\Services\Soporte\ReglasOperacion;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SolicitudRegistroService
{
    public function __construct(
        private readonly AutorizacionInstitucional $acceso,
        private readonly AuditoriaService $auditoria,
        private readonly ReglasOperacion $reglas,
    ) {}

    /** Operación pública: solo recibe datos de contacto, sin roles ni estados. */
    public function crear(int $institucionId, array $datos): SolicitudRegistro
    {
        $datos = Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:120'],
            'apellidos' => ['required', 'string', 'max:160'],
            'correo' => ['required', 'email', 'max:190'],
        ])->validate();

        return DB::transaction(function () use ($institucionId, $datos): SolicitudRegistro {
            Institucion::query()->where('estado', 'activa')->lockForUpdate()->findOrFail($institucionId);
            $correo = Str::lower(trim($datos['correo']));
            $pendiente = $this->reglas->estado('solicitud.pendiente');
            if (SolicitudRegistro::query()->where('institucion_id', $institucionId)->where('correo', $correo)
                ->where('estado', $pendiente)->lockForUpdate()->first()) {
                throw ValidationException::withMessages(['correo' => 'Ya existe una solicitud pendiente para este correo en la institución.']);
            }

            $solicitud = new SolicitudRegistro;
            $solicitud->institucion_id = $institucionId;
            $solicitud->nombre = $datos['nombre'];
            $solicitud->apellidos = $datos['apellidos'];
            $solicitud->correo = $correo;
            $solicitud->estado = $pendiente;
            $solicitud->save();
            $this->auditoria->registrar(null, $institucionId, 'solicitudes.crear', $solicitud);

            return $solicitud;
        }, 3);
    }

    public function consultar(User $actor, int $institucionId, int $solicitudId): SolicitudRegistro
    {
        $this->acceso->usuario($actor, $institucionId, 'solicitudes.revisar');

        return SolicitudRegistro::query()->where('institucion_id', $institucionId)->findOrFail($solicitudId);
    }

    /** Provisiona únicamente Participante pendiente; no activa ni envía correos. */
    public function aprobar(User $actor, int $institucionId, int $solicitudId): SolicitudRegistro
    {
        try {
            return DB::transaction(function () use ($actor, $institucionId, $solicitudId): SolicitudRegistro {
                $actor = $this->acceso->usuario($actor, $institucionId, 'solicitudes.revisar');
                $this->acceso->exigirRol($actor, $institucionId, 'administrador');
                $aprobada = $this->reglas->estado('solicitud.aprobada');
                $solicitud = SolicitudRegistro::query()->where('institucion_id', $institucionId)
                    ->lockForUpdate()->findOrFail($solicitudId);
                if ($solicitud->estado === $aprobada && $solicitud->usuario_vinculado_id !== null) {
                    return $solicitud;
                }
                $this->exigirPendiente($solicitud);
                Validator::make(['correo' => $solicitud->correo], ['correo' => ['required', 'email', 'max:190']])->validate();
                foreach ($solicitud->documentos()->lockForUpdate()->get() as $documento) {
                    if ($documento->estado_revision !== $this->reglas->estado('documento.aprobado')) {
                        throw ValidationException::withMessages(['documentos' => 'Todos los documentos adjuntos deben haber sido revisados y aprobados.']);
                    }
                }

                // La coincidencia de correo no demuestra titularidad de una cuenta.
                if (User::withTrashed()->where('email', $solicitud->correo)->exists()) {
                    throw ValidationException::withMessages(['correo' => 'El correo ya pertenece a una cuenta. Su vinculación necesita un flujo de verificación de identidad.']);
                }
                $rol = Rol::query()->where('clave', 'participante')->first();
                if (! $rol) {
                    throw ValidationException::withMessages(['rol' => 'No existe el rol participante. No se crearán ni asignarán otros roles.']);
                }
                $nombre = trim($solicitud->nombre.' '.$solicitud->apellidos);
                if (mb_strlen($nombre) > 255) {
                    throw ValidationException::withMessages(['nombre' => 'El nombre completo excede los 255 caracteres permitidos en users.name.']);
                }

                $usuario = new User;
                $usuario->name = $nombre;
                $usuario->email = $solicitud->correo;
                $usuario->password = bin2hex(random_bytes(64)); // El cast original aplica el hash.
                $usuario->estado_cuenta = 'pendiente';
                $usuario->save();

                $participante = new Participante;
                $participante->user_id = $usuario->getKey();
                $participante->fecha_alta = today();
                $participante->save();

                $asignacion = new UsuarioRol;
                $asignacion->user_id = $usuario->getKey();
                $asignacion->rol_id = $rol->getKey();
                $asignacion->institucion_id = $institucionId;
                $asignacion->asignado_por = $actor->getKey();
                $asignacion->save();

                $solicitud->estado = $aprobada;
                $solicitud->revisado_por = $actor->getKey();
                $solicitud->usuario_vinculado_id = $usuario->getKey();
                $solicitud->fecha_revision = now();
                $solicitud->save();
                $this->auditoria->registrar($actor, $institucionId, 'solicitudes.aprobar', $solicitud, [
                    'usuario_id' => $usuario->getKey(), 'participante_id' => $participante->getKey(),
                    'rol_id' => $rol->getKey(), 'asignacion_id' => $asignacion->getKey(), 'cuenta_activada' => false,
                ]);

                return $solicitud;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['correo' => 'Ya existe una cuenta o asignación concurrente. La operación se revirtió.']);
        }
    }

    public function rechazar(User $actor, int $institucionId, int $solicitudId, string $motivo): SolicitudRegistro
    {
        $datos = Validator::make(['motivo' => $motivo], ['motivo' => ['required', 'string', 'max:4000']])->validate();

        return DB::transaction(function () use ($actor, $institucionId, $solicitudId, $datos): SolicitudRegistro {
            $actor = $this->acceso->usuario($actor, $institucionId, 'solicitudes.revisar');
            $rechazada = $this->reglas->estado('solicitud.rechazada');
            $solicitud = SolicitudRegistro::query()->where('institucion_id', $institucionId)->lockForUpdate()->findOrFail($solicitudId);
            if ($solicitud->estado === $rechazada && $solicitud->motivo_revision === $datos['motivo']) {
                return $solicitud;
            }
            $this->exigirPendiente($solicitud);
            $solicitud->estado = $rechazada;
            $solicitud->revisado_por = $actor->getKey();
            $solicitud->motivo_revision = $datos['motivo'];
            $solicitud->fecha_revision = now();
            $solicitud->save();
            $this->auditoria->registrar($actor, $institucionId, 'solicitudes.rechazar', $solicitud);

            return $solicitud;
        }, 3);
    }

    private function exigirPendiente(SolicitudRegistro $solicitud): void
    {
        if ($solicitud->estado !== $this->reglas->estado('solicitud.pendiente') || $solicitud->usuario_vinculado_id !== null) {
            throw ValidationException::withMessages(['solicitud' => 'La solicitud ya fue revisada o vinculada; no se puede cambiar por este flujo.']);
        }
    }
}
