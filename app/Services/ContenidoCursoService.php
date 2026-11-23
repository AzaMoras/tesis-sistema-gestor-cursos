<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\MaterialCurso;
use App\Models\ModuloCurso;
use App\Models\User;
use App\Services\Soporte\AuditoriaService;
use App\Services\Soporte\UrlMaterial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ContenidoCursoService
{
    // tipo es VARCHAR(25), sin ENUM ni catálogo anterior: subconjunto operativo de esta fase.
    public const TIPOS = ['enlace' => 'Enlace educativo', 'video' => 'Video externo', 'documento' => 'Documento externo'];

    public function __construct(private GestionOfertaService $oferta, private AuditoriaService $auditoria) {}

    public function curso(User $actor, int $institucion, int $curso): Curso
    {
        $this->oferta->autorizar($actor, $institucion);

        return $this->oferta->consulta('cursos', $institucion)->findOrFail($curso);
    }

    public function modulo(Curso $curso, int $modulo): ModuloCurso
    {
        return $curso->modulos()->findOrFail($modulo);
    }

    public function material(ModuloCurso $modulo, int $material): MaterialCurso
    {
        return $modulo->materiales()->findOrFail($material);
    }

    /** Sin versiones persistidas, se protege todo el curso con evidencias académicas. */
    public function protegido(Curso $curso): bool
    {
        return DB::table('entregas')->join('actividades', 'actividades.id', '=', 'entregas.actividad_id')
            ->where('actividades.curso_id', $curso->id)->exists()
            || DB::table('constancias')->join('inscripciones', 'inscripciones.id', '=', 'constancias.inscripcion_id')
                ->join('grupos', 'grupos.id', '=', 'inscripciones.grupo_id')
                ->join('ediciones', 'ediciones.id', '=', 'grupos.edicion_id')->where('ediciones.curso_id', $curso->id)->exists();
    }

    public function guardarModulo(User $actor, int $institucion, int $cursoId, ?int $moduloId, array $datos): ModuloCurso
    {
        return $this->escribir($actor, $institucion, $cursoId, function (Curso $curso) use ($actor, $institucion, $moduloId, $datos) {
            $modulo = $moduloId ? $this->modulo($curso, $moduloId) : new ModuloCurso;
            $this->campos($datos, ['titulo', 'descripcion', 'orden', 'obligatorio']);
            $valores = Validator::make($datos, [
                'titulo' => ['required', 'string', 'max:180'], 'descripcion' => ['nullable', 'string', 'max:10000'],
                'orden' => ['required', 'integer', 'between:1,65535', Rule::unique('modulos_curso', 'orden')->where('curso_id', $curso->id)->ignore($modulo->id)],
                'obligatorio' => ['required', 'boolean'],
            ], $this->mensajes())->validate();
            if ($modulo->exists && $modulo->actividades()->withTrashed()->exists()
                && ((int) $valores['orden'] !== $modulo->orden || (bool) $valores['obligatorio'] !== $modulo->obligatorio)) {
                $this->error('contenido', 'El orden y el carácter obligatorio se conservan en módulos vinculados a actividades.');
            }
            $anterior = $modulo->getAttributes();
            $modulo->fill($valores);
            $modulo->curso_id = $curso->id;
            $modulo->save();
            $this->auditoria->registrar($actor, $institucion, 'contenido.modulo.guardar', $modulo, ['anterior' => $anterior, 'nuevo' => $modulo->getAttributes()]);

            return $modulo;
        });
    }

    public function guardarMaterial(User $actor, int $institucion, int $cursoId, int $moduloId, ?int $materialId, array $datos): MaterialCurso
    {
        return $this->escribir($actor, $institucion, $cursoId, function (Curso $curso) use ($actor, $institucion, $moduloId, $materialId, $datos) {
            $modulo = $this->modulo($curso, $moduloId);
            $material = $materialId ? $this->material($modulo, $materialId) : new MaterialCurso;
            if ($material->publicado || $material->ruta_archivo !== null) {
                $this->error('contenido', 'Solo se editan borradores externos. Oculta el material mediante su acción específica antes de corregirlo.');
            }
            $this->campos($datos, ['titulo', 'tipo', 'url_externa', 'orden']);
            $valores = $this->validarMaterial($datos, $modulo, $material);
            $anterior = $material->getAttributes();
            $material->fill($valores);
            $material->modulo_id = $modulo->id;
            $material->publicado = false;
            $material->save();
            $this->auditoria->registrar($actor, $institucion, 'contenido.material.guardar', $material, ['anterior' => $anterior, 'nuevo' => $material->getAttributes()]);

            return $material;
        });
    }

    public function publicarMaterial(User $actor, int $institucion, int $cursoId, int $moduloId, int $materialId, bool $publicar): MaterialCurso
    {
        return $this->escribir($actor, $institucion, $cursoId, function (Curso $curso) use ($actor, $institucion, $moduloId, $materialId, $publicar) {
            $modulo = $this->modulo($curso, $moduloId);
            $material = $this->material($modulo, $materialId);
            if ($material->publicado === $publicar) {
                $this->error('publicado', 'El material ya tiene ese estado de publicación.');
            }
            if ($publicar) {
                if ($material->ruta_archivo !== null) {
                    $this->error('contenido', 'La publicación de archivos almacenados no está disponible en esta fase.');
                }
                $this->validarMaterial($material->only(['titulo', 'tipo', 'url_externa', 'orden']), $modulo, $material);
            }
            $material->publicado = $publicar;
            $material->save();
            $this->auditoria->registrar($actor, $institucion, $publicar ? 'contenido.material.publicar' : 'contenido.material.ocultar', $material, ['publicado' => $publicar]);

            return $material;
        });
    }

    private function escribir(User $actor, int $institucion, int $cursoId, callable $operacion): Model
    {
        $this->oferta->autorizar($actor, $institucion);

        return DB::transaction(function () use ($actor, $institucion, $cursoId, $operacion) {
            // Mismo cerrojo institucional que la gestión de oferta; serializa órdenes únicos.
            DB::table('instituciones')->where('id', $institucion)->lockForUpdate()->first();
            $this->oferta->autorizar($actor, $institucion);
            $curso = $this->oferta->consulta('cursos', $institucion)->lockForUpdate()->findOrFail($cursoId);
            if ($this->protegido($curso)) {
                $this->error('contenido', 'El curso tiene evidencias académicas o constancias. Su contenido queda protegido hasta disponer de una política de versiones.');
            }

            return $operacion($curso);
        }, 3);
    }

    private function validarMaterial(array $datos, ModuloCurso $modulo, MaterialCurso $material): array
    {
        return Validator::make($datos, [
            'titulo' => ['required', 'string', 'max:180'], 'tipo' => ['required', Rule::in(array_keys(self::TIPOS))],
            'url_externa' => ['required', 'string', 'max:1000', function ($atributo, $valor, $fallar) {
                if (! UrlMaterial::segura($valor)) {
                    $fallar('Usa un enlace HTTPS externo válido, sin credenciales ni direcciones locales.');
                }
            }],
            'orden' => ['required', 'integer', 'between:1,65535', Rule::unique('materiales_curso', 'orden')->where('modulo_id', $modulo->id)->ignore($material->id)],
        ], $this->mensajes())->validate();
    }

    private function campos(array $datos, array $permitidos): void
    {
        foreach (array_diff(array_keys($datos), [...$permitidos, '_token', '_method']) as $campo) {
            $this->error($campo, 'Este formulario no permite cambiar relaciones, publicación ni otros campos protegidos.');
        }
    }

    private function mensajes(): array
    {
        return ['required' => 'Este campo es obligatorio.', 'string' => 'Escribe un texto válido.', 'max' => 'Se supera la longitud máxima permitida.',
            'integer' => 'El orden debe ser un número entero.', 'between' => 'El orden debe estar entre 1 y 65535.',
            'unique' => 'Ese orden ya está ocupado, incluso por un registro histórico. Elige un número libre.',
            'boolean' => 'Selecciona una opción válida.', 'in' => 'Selecciona un tipo de material permitido.'];
    }

    private function error(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
