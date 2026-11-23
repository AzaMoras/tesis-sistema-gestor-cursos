<?php

namespace App\Services;

use App\Models\Inscripcion;
use App\Models\User;
use App\Services\Soporte\AutorizacionInstitucional;
use App\Services\Soporte\ReglasOperacion;
use App\Services\Soporte\UrlMaterial;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class AulaVirtualService
{
    public function __construct(private AutorizacionInstitucional $acceso, private ReglasOperacion $reglas, private VigenciaAccesoService $vigencia) {}

    /** No hay excepción administrativa: esta ruta siempre es del participante propietario. */
    public function consultar(User $actor, int $id): array
    {
        $usuario = User::query()->where('estado_cuenta', 'activa')->find($actor->getKey());
        if (! $usuario) {
            throw new AuthorizationException('La cuenta no está activa.');
        }
        $participante = $usuario->participante()->first();
        abort_unless($participante, 403, 'Tu cuenta no tiene un perfil de participante activo.');
        $inscripcion = Inscripcion::query()->where('participante_id', $participante->id)
            ->with('grupo.edicion.curso.institucion')->findOrFail($id);
        $curso = $inscripcion->grupo?->edicion?->curso;
        if (! $curso || ! $curso->institucion || $curso->institucion->estado !== 'activa') {
            return $this->resultado($inscripcion, false, 'El curso o su institución no están disponibles. Tu historial se conserva.');
        }
        // Reconstruye la cadena completa y verifica también la categoría y el rol institucional.
        $this->acceso->usuario($usuario, $curso->institucion_id);
        $inscripcion = $this->acceso->inscripcion($curso->institucion_id, $inscripcion->id);
        if ($inscripcion->estado !== $this->reglas->estado('inscripcion.confirmada')
            || ! $inscripcion->fecha_inscripcion || $inscripcion->fecha_inscripcion->gt(now())
            || $inscripcion->fecha_cancelacion !== null) {
            return $this->resultado($inscripcion, false, 'El aula requiere una inscripción confirmada y no cancelada.');
        }
        $edicion = $inscripcion->grupo->edicion;
        $columnas = Schema::hasColumns('ediciones', ['tipo_vigencia', 'duracion_acceso_dias', 'prorroga_gratuita_dias'])
            && Schema::hasColumns('inscripciones', ['acceso_inicia_at', 'acceso_vence_at', 'prorroga_gratuita_dias', 'prorroga_gratuita_aplicada_at']);
        $configurada = $edicion->tipo_vigencia !== null || $edicion->duracion_acceso_dias !== null || $edicion->prorroga_gratuita_dias !== null
            || $inscripcion->acceso_inicia_at !== null || $inscripcion->acceso_vence_at !== null
            || $inscripcion->prorroga_gratuita_dias !== null || $inscripcion->prorroga_gratuita_aplicada_at !== null;
        if ($configurada) {
            if (! $columnas) {
                return $this->resultado($inscripcion, false, 'La configuración de vigencia está incompleta. Contacta a la institución.');
            }
            try {
                $this->vigencia->exigirPuedeIniciar($usuario, $curso->institucion_id, $inscripcion->id);
            } catch (ValidationException $error) {
                return $this->resultado($inscripcion, false, collect($error->errors())->flatten()->first());
            }

            return $this->resultado($inscripcion, true, $inscripcion->acceso_vence_at
                ? 'Acceso vigente hasta '.$inscripcion->acceso_vence_at->format('d/m/Y H:i').' UTC.'
                : 'Acceso sin vencimiento según la activación registrada por la institución.');
        }

        // Consulta provisional de recursos, no activación ni política académica indefinida.
        return $this->resultado($inscripcion, true, 'Consulta de materiales habilitada por tu inscripción confirmada. La vigencia académica está pendiente de configurar; esto no implica acceso indefinido.');
    }

    public function disponibilidad(User $actor, int $id): array
    {
        try {
            return $this->consultar($actor, $id);
        } catch (AuthorizationException|ModelNotFoundException $error) {
            return ['permitido' => false, 'mensaje' => 'El acceso al aula no está disponible para esta inscripción. Tu historial se conserva.'];
        }
    }

    public function modulos(Inscripcion $inscripcion)
    {
        return $inscripcion->grupo->edicion->curso->modulos()->orderBy('orden')
            ->with(['materiales' => fn ($q) => $q->where('publicado', true)->whereNull('ruta_archivo')->orderBy('orden')])->get()
            ->each(fn ($modulo) => $modulo->setRelation('materiales', $modulo->materiales
                ->filter(fn ($material) => isset(ContenidoCursoService::TIPOS[$material->tipo]) && UrlMaterial::segura($material->url_externa))->values()));
    }

    private function resultado(Inscripcion $inscripcion, bool $permitido, string $mensaje): array
    {
        return compact('inscripcion', 'permitido', 'mensaje');
    }
}
