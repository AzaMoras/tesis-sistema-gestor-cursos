<?php

namespace App\Services;

use App\Models\CategoriaCurso;
use App\Models\Curso;
use App\Models\Edicion;
use App\Models\Grupo;
use App\Models\Institucion;
use App\Models\User;
use App\Services\Soporte\AuditoriaService;
use App\Services\Soporte\AutorizacionInstitucional;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GestionOfertaService
{
    public const TIPOS = [
        'categorias' => CategoriaCurso::class,
        'cursos' => Curso::class,
        'ediciones' => Edicion::class,
        'grupos' => Grupo::class,
    ];

    public function __construct(private AutorizacionInstitucional $acceso, private AuditoriaService $auditoria) {}

    public function autorizar(User $actor, int $institucion): void
    {
        $usuario = $this->acceso->usuario($actor, $institucion, 'cursos.gestionar');
        if (! $usuario->rolesEnInstitucion($institucion)->whereIn('roles.clave', ['administrador', 'coordinador'])->exists()) {
            throw new AuthorizationException('Se requiere personal de gestión autorizado.');
        }
    }

    public function institucionesAutorizadas(User $actor): Collection
    {
        $usuario = User::query()->find($actor->getKey());
        if (! $usuario || $usuario->estado_cuenta !== 'activa') {
            return collect();
        }

        return Institucion::query()->where('estado', 'activa')
            ->whereHas('usuarios', fn ($q) => $q->whereKey($usuario->id))->get()
            ->filter(fn ($institucion) => $usuario->rolesEnInstitucion($institucion->id)
                ->whereIn('roles.clave', ['administrador', 'coordinador'])->exists()
                && $usuario->permisosEnInstitucion($institucion->id)->where('clave', 'cursos.gestionar')->exists());
    }

    public function tieneInscripciones(Curso $curso, bool $bloquear = false): bool
    {
        // Incluye ediciones/grupos eliminados lógicamente e inscripciones históricas.
        return DB::table('inscripciones')->join('grupos', 'grupos.id', '=', 'inscripciones.grupo_id')
            ->join('ediciones', 'ediciones.id', '=', 'grupos.edicion_id')->where('ediciones.curso_id', $curso->id)
            ->when($bloquear, fn ($q) => $q->sharedLock())->first(['inscripciones.id']) !== null;
    }

    public function camposGeneralesCurso(Curso $curso, bool $bloquear = false): array
    {
        return $this->tieneInscripciones($curso, $bloquear) ? ['titulo'] : ['titulo', 'descripcion', 'objetivos'];
    }

    public function consulta(string $tipo, int $institucion): Builder
    {
        abort_unless(isset(self::TIPOS[$tipo]), 404);

        return match ($tipo) {
            'categorias' => CategoriaCurso::query()->where('institucion_id', $institucion),
            'cursos' => Curso::query()->where('institucion_id', $institucion)
                ->whereHas('categoria', fn ($q) => $q->where('institucion_id', $institucion))->with('categoria'),
            'ediciones' => Edicion::query()->whereHas('curso', fn ($q) => $q->where('institucion_id', $institucion)
                ->whereHas('categoria', fn ($c) => $c->where('institucion_id', $institucion)))->with('curso'),
            'grupos' => Grupo::query()->whereHas('edicion.curso', fn ($q) => $q->where('institucion_id', $institucion)
                ->whereHas('categoria', fn ($c) => $c->where('institucion_id', $institucion)))->with('edicion.curso'),
        };
    }

    public function editable(string $tipo, Model $registro): bool
    {
        if ($tipo === 'cursos' && $registro->estado === config('operacion.publicacion.cursos')) {
            return true;
        }
        if ($tipo === 'categorias') {
            return $registro->estado === 'activa' && ! $registro->cursos()->where('estado', '!=', 'borrador')->exists();
        }
        if ($registro->estado !== 'borrador') {
            return false;
        }

        return match ($tipo) {
            'cursos' => ! $registro->ediciones()->where(fn ($q) => $q->where('estado', '!=', 'borrador')->orWhereHas('grupos.inscripciones'))->exists(),
            'ediciones' => ! $registro->grupos()->where(fn ($q) => $q->where('estado', '!=', 'borrador')->orWhereHas('inscripciones'))->exists(),
            'grupos' => ! $registro->inscripciones()->exists(),
        };
    }

    public function guardar(User $actor, int $institucion, string $tipo, ?int $id, array $datos): Model
    {
        $this->autorizar($actor, $institucion);
        abort_if($id !== null && $id < 1, 404);

        return DB::transaction(function () use ($actor, $institucion, $tipo, $id, $datos) {
            // Serializa las escrituras de esta gestión y las comprobaciones de unicidad.
            $this->acceso->institucion($institucion)->newQuery()->whereKey($institucion)->lockForUpdate()->firstOrFail();
            $this->autorizar($actor, $institucion);
            $registro = $id ? $this->consulta($tipo, $institucion)->lockForUpdate()->findOrFail($id) : new (self::TIPOS[$tipo]);
            if ($id && array_key_exists('estado', $datos)) {
                $this->error('estado', 'El estado se modifica únicamente mediante la acción de publicación correspondiente.');
            }
            if ($id && ! $this->editable($tipo, $registro)) {
                $this->error('estado', 'Este registro está protegido por su estado o por sus relaciones operativas.');
            }
            $camposCurso = null;
            if ($id && $tipo === 'cursos') {
                $publicado = $registro->estado === config('operacion.publicacion.cursos');
                $permitidos = ['titulo', 'descripcion', 'objetivos', 'categoria_id', '_token', '_method'];
                foreach (array_diff(array_keys($datos), $permitidos) as $campo) {
                    $this->error($campo, 'Esta edición no permite modificar institución, requisitos, condiciones ni datos académicos.');
                }
                if ($publicado) {
                    $camposCurso = $this->camposGeneralesCurso($registro, true);
                    if (array_key_exists('categoria_id', $datos)
                        && filter_var($datos['categoria_id'], FILTER_VALIDATE_INT) !== (int) $registro->categoria_id) {
                        $this->error('categoria_id', 'La categoría de un curso publicado se conserva.');
                    }
                    foreach (array_diff(['descripcion', 'objetivos'], $camposCurso) as $campo) {
                        if (array_key_exists($campo, $datos)
                            && (! is_scalar($datos[$campo]) && $datos[$campo] !== null
                                || trim((string) $datos[$campo]) !== trim((string) $registro->$campo))) {
                            $this->error($campo, 'Con inscripciones existentes se conservan la descripción y los objetivos ofrecidos. Solo puedes corregir el título.');
                        }
                    }
                }
            }
            $valores = $this->validar($tipo, $institucion, $id, $datos, $camposCurso, $registro);
            foreach (['curso_id', 'edicion_id'] as $padre) {
                if ($id && isset($valores[$padre]) && (int) $registro->$padre !== (int) $valores[$padre]) {
                    $this->error($padre, 'No se permite trasladar el registro a otro curso o edición.');
                }
            }
            if (in_array($tipo, ['categorias', 'cursos'], true) && ! $id) {
                $valores['institucion_id'] = $institucion;
            }
            $anterior = $registro->exists ? $registro->getAttributes() : null;
            $registro->forceFill($valores);
            if (! $id) {
                $registro->estado = $tipo === 'categorias' ? 'activa' : 'borrador';
            }
            $registro->save();
            $this->auditoria->registrar($actor, $institucion, $tipo.($id ? '.editar' : '.crear'), $registro, [
                'anterior' => $anterior, 'nuevo' => $registro->getAttributes(),
                'alcance' => $camposCurso !== null ? 'informacion_general' : 'gestion_borrador',
                'campos_modificados' => array_keys($registro->getChanges()),
            ]);

            return $registro;
        }, 3);
    }

    private function validar(string $tipo, int $institucion, ?int $id, array $datos, ?array $camposCurso = null, ?Model $original = null, bool $abrir = false): array
    {
        foreach ($datos as $clave => $valor) {
            if (is_string($valor)) {
                $datos[$clave] = trim($valor);
            }
        }
        if ($tipo === 'ediciones') {
            $datos = $this->fechasDelFormulario($datos);
        }
        $reglas = match ($tipo) {
            'categorias' => [
                'nombre' => ['required', 'string', 'max:120'],
                'slug' => ['required', 'string', 'max:150', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique('categorias_curso')->where('institucion_id', $institucion)->ignore($id)],
                'descripcion' => ['nullable', 'string', 'max:10000'],
            ],
            'cursos' => [
                'categoria_id' => ['required', 'integer', Rule::exists('categorias_curso', 'id')->where('institucion_id', $institucion)->where('estado', 'activa')->whereNull('deleted_at')],
                'titulo' => ['required', 'string', 'max:180'],
                'descripcion' => ['required', 'string', 'max:20000'],
                'objetivos' => ['nullable', 'string', 'max:10000'],
            ],
            'ediciones' => [
                'curso_id' => ['required', 'integer'],
                'clave' => ['required', 'string', 'max:60', Rule::unique('ediciones')->where('curso_id', is_scalar($datos['curso_id'] ?? null) ? $datos['curso_id'] : null)->ignore($id)],
                'modalidad' => ['required', Rule::in(['autogestionado', 'acompanada'])],
                'fecha_inicio' => ['required', 'date_format:Y-m-d'],
                'fecha_fin' => ['required', 'date_format:Y-m-d'],
                'cierre_inscripciones_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
                'precio' => ['required', 'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/'],
                'moneda' => ['required', Rule::in(['MXN'])],
            ],
            'grupos' => [
                'edicion_id' => ['required', 'integer'],
                'codigo' => ['required', 'string', 'max:60', Rule::unique('grupos')->where('edicion_id', is_scalar($datos['edicion_id'] ?? null) ? $datos['edicion_id'] : null)->ignore($id)],
                'cupo_maximo' => ['required', 'integer', 'min:1', 'max:4294967295'],
            ],
        };
        if ($tipo === 'cursos' && $camposCurso !== null) {
            $reglas = array_intersect_key($reglas, array_flip($camposCurso));
        }
        $valores = Validator::make($datos, $reglas, [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'max' => 'El campo :attribute supera el máximo permitido (:max).',
            'min' => 'El campo :attribute debe ser al menos :min.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'unique' => 'El valor de :attribute ya existe en este ámbito, incluido el histórico.',
            'exists' => 'La referencia de :attribute no está disponible en esta institución.',
            'in' => 'Selecciona un valor permitido para :attribute.',
            'regex' => 'El formato de :attribute no es válido.',
            'date_format' => 'El formato de :attribute no es válido.',
            'after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
            'before_or_equal' => 'El cierre de inscripciones no puede ser posterior a la fecha final.',
        ], ['categoria_id' => 'categoría', 'curso_id' => 'curso', 'edicion_id' => 'edición', 'cupo_maximo' => 'cupo', 'cierre_inscripciones_at' => 'cierre de inscripciones'])->validate();
        if ($tipo === 'ediciones') {
            $valores = $this->validarFechasEdicion($valores, $original instanceof Edicion && $original->exists ? $original : null, $abrir);
        }
        if ($tipo === 'ediciones' && ! $this->consulta('cursos', $institucion)->whereKey($valores['curso_id'])->exists()) {
            $this->error('curso_id', 'El curso no está disponible en esta institución.');
        }
        if ($tipo === 'grupos' && ! $this->consulta('ediciones', $institucion)->whereKey($valores['edicion_id'])->exists()) {
            $this->error('edicion_id', 'La edición no está disponible en esta institución.');
        }

        return $valores;
    }

    /** El texto visible también funciona sin JavaScript; el dominio sigue recibiendo ISO. */
    private function fechasDelFormulario(array $datos): array
    {
        foreach (['fecha_inicio', 'fecha_fin', 'cierre_inscripciones_at'] as $campo) {
            if (! array_key_exists($campo.'_mexicana', $datos)) {
                continue;
            }
            $formato = $campo === 'cierre_inscripciones_at' ? 'd/m/Y H:i' : 'd/m/Y';
            $valor = $datos[$campo.'_mexicana'];
            Validator::make([$campo => $valor], [$campo => ['nullable', 'string', 'date_format:'.$formato]], [
                'string' => 'Escribe una fecha válida en formato mexicano.',
                'date_format' => $campo === 'cierre_inscripciones_at'
                    ? 'Escribe una fecha y hora válidas como 19/11/2026 22:30 (24 horas).'
                    : 'Escribe una fecha válida como 19/11/2026 (dd/mm/aaaa).',
            ])->validate();
            $datos[$campo] = $valor === null || $valor === '' ? null
                : CarbonImmutable::createFromFormat('!'.$formato, $valor, config('app.timezone'))
                    ->format($campo === 'cierre_inscripciones_at' ? 'Y-m-d\TH:i' : 'Y-m-d');
        }

        return $datos;
    }

    private function validarFechasEdicion(array $datos, ?Edicion $original, bool $abrir): array
    {
        $ahora = now()->toImmutable();
        $hoy = $ahora->startOfDay();
        $cambios = [];
        foreach (['fecha_inicio', 'fecha_fin', 'cierre_inscripciones_at'] as $campo) {
            $formato = $campo === 'cierre_inscripciones_at' ? 'Y-m-d\TH:i' : 'Y-m-d';
            $cambios[$campo] = ! $original || ($datos[$campo] ?? null) !== $original->$campo?->format($formato);
        }
        $inicio = CarbonImmutable::createFromFormat('!Y-m-d', $datos['fecha_inicio'], config('app.timezone'));
        $fin = CarbonImmutable::createFromFormat('!Y-m-d', $datos['fecha_fin'], config('app.timezone'));
        if ($cambios['fecha_inicio'] && $inicio->lt($hoy)) {
            $this->error('fecha_inicio', 'La fecha inicial nueva debe ser hoy o una fecha futura.');
        }
        if (($cambios['fecha_fin'] || $abrir) && $fin->lt($hoy)) {
            $this->error('fecha_fin', 'La fecha final no puede quedar en el pasado.');
        }
        if (($cambios['fecha_inicio'] || $cambios['fecha_fin'] || $abrir) && $fin->lt($inicio)) {
            $this->error('fecha_fin', 'La fecha final debe ser igual o posterior a la inicial.');
        }
        $cierre = ! empty($datos['cierre_inscripciones_at'])
            ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $datos['cierre_inscripciones_at'], config('app.timezone')) : null;
        if ($original && ! $cambios['cierre_inscripciones_at']) {
            // El formulario tiene precisión de minutos: conservar los segundos históricos.
            $cierre = $original->cierre_inscripciones_at?->toImmutable();
            $datos['cierre_inscripciones_at'] = $cierre?->format('Y-m-d H:i:s');
        }
        if ($cierre && ($cambios['cierre_inscripciones_at'] || $abrir) && $cierre->lte($ahora)) {
            $this->error('cierre_inscripciones_at', 'El cierre de inscripciones debe ser posterior a la hora actual del servidor.');
        }
        if ($cierre && ($cambios['cierre_inscripciones_at'] || $cambios['fecha_fin'] || $abrir) && $cierre->gte($fin->addDay())) {
            $this->error('cierre_inscripciones_at', 'El cierre no puede superar el final del día de la fecha final (23:59).');
        }

        return $datos;
    }

    public function publicar(User $actor, int $institucion, string $tipo, int $id): void
    {
        $this->autorizar($actor, $institucion);
        abort_unless(in_array($tipo, ['cursos', 'ediciones', 'grupos'], true), 404);
        DB::transaction(function () use ($actor, $institucion, $tipo, $id) {
            $this->acceso->institucion($institucion)->newQuery()->whereKey($institucion)->lockForUpdate()->firstOrFail();
            $this->autorizar($actor, $institucion);
            $registro = $this->consulta($tipo, $institucion)->lockForUpdate()->findOrFail($id);
            if ($registro->estado !== 'borrador') {
                $this->error('estado', 'La transición requiere un registro en borrador.');
            }
            $curso = match ($tipo) {
                'cursos' => $registro,
                'ediciones' => $registro->curso,
                'grupos' => $registro->edicion->curso,
            };
            if ($curso->categoria->estado !== 'activa') {
                $this->error('categoria_id', 'La categoría debe estar activa.');
            }
            if ($tipo === 'ediciones' && $registro->curso->estado !== config('operacion.publicacion.cursos')) {
                $this->error('estado', 'Publica primero el curso.');
            }
            if ($tipo === 'grupos' && ($registro->edicion->estado !== config('operacion.publicacion.ediciones')
                || $registro->edicion->curso->estado !== config('operacion.publicacion.cursos'))) {
                $this->error('estado', 'Publica el curso y abre su edición antes del grupo.');
            }
            // Revalida registros históricos antes de habilitarlos.
            $this->validar($tipo, $institucion, $id, $this->datosDe($tipo, $registro), original: $registro, abrir: true);
            $anterior = $registro->estado;
            $registro->estado = config('operacion.publicacion.'.$tipo);
            $registro->save();
            $this->auditoria->registrar($actor, $institucion, $tipo.'.publicar', $registro, ['anterior' => $anterior, 'nuevo' => $registro->estado]);
        }, 3);
    }

    public function datosDe(string $tipo, Model $registro): array
    {
        $datos = $registro->getAttributes();
        if ($tipo === 'ediciones') {
            $datos['fecha_inicio'] = $registro->fecha_inicio?->format('Y-m-d');
            $datos['fecha_fin'] = $registro->fecha_fin?->format('Y-m-d');
            $datos['cierre_inscripciones_at'] = $registro->cierre_inscripciones_at?->format('Y-m-d\TH:i');
        }

        return $datos;
    }

    private function error(string $campo, string $mensaje): never
    {
        throw ValidationException::withMessages([$campo => $mensaje]);
    }
}
