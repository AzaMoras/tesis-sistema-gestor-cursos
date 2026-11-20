<?php

namespace Tests\Support;

use App\Models\CategoriaCurso;
use App\Models\Curso;
use App\Models\Edicion;
use App\Models\Grupo;
use App\Models\Institucion;
use App\Models\Rol;
use App\Models\User;
use App\Services\RegistroParticipanteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class InscripcionPublicaTestCase extends TestCase
{
    use RefreshDatabase;

    protected Institucion $institucion;

    protected CategoriaCurso $categoria;

    protected Curso $curso;

    protected Edicion $edicion;

    protected Grupo $grupo;

    protected Rol $rol;

    protected User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(12, 0));
        config(['auth.verification.enabled' => false]);
        $this->institucion = $this->record(Institucion::class, ['nombre' => 'Institución de prueba', 'slug' => 'prueba', 'estado' => 'activa']);
        $this->categoria = $this->record(CategoriaCurso::class, ['institucion_id' => $this->institucion->id, 'nombre' => 'Tecnología', 'slug' => 'tecnologia']);
        $this->curso = $this->record(Curso::class, ['institucion_id' => $this->institucion->id, 'categoria_id' => $this->categoria->id,
            'titulo' => 'Hardware introductorio', 'descripcion' => 'Descripción pública.', 'objetivos' => 'Reconocer componentes.', 'estado' => 'publicado']);
        $this->edicion = $this->record(Edicion::class, ['curso_id' => $this->curso->id, 'clave' => 'ED-2026', 'modalidad' => 'autogestionado',
            'fecha_inicio' => '2026-11-23', 'fecha_fin' => '2026-12-23', 'cierre_inscripciones_at' => '2026-11-22 18:00:00',
            'precio' => '0.00', 'moneda' => 'MXN', 'estado' => 'abierta']);
        $this->grupo = $this->record(Grupo::class, ['edicion_id' => $this->edicion->id, 'codigo' => 'GR-01', 'cupo_maximo' => 2, 'estado' => 'abierta']);
        $this->rol = $this->record(Rol::class, ['clave' => 'participante', 'nombre' => 'Participante']);
        $this->usuario = $this->nuevoParticipante();
    }

    protected function record(string $class, array $attributes): Model
    {
        $record = new $class;
        $record->forceFill($attributes)->save();

        return $record;
    }

    protected function nuevoParticipante(): User
    {
        return app(RegistroParticipanteService::class)->registrar('Persona de prueba', uniqid().'@example.test', 'UnaClaveSegura!123');
    }

    protected function destino(): string
    {
        return '/cursos/'.$this->curso->id.'/grupos/'.$this->grupo->id.'/inscripcion';
    }
}
