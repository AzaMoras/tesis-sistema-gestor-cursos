<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Requisito extends Model
{
    protected $table = 'requisitos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'requiere_documento',
    ];

    protected function casts(): array
    {
        return [
            'requiere_documento' => 'boolean',
        ];
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function cursos(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_requisito', 'requisito_id', 'curso_id')
            ->using(CursoRequisito::class)
            ->withPivot('obligatorio');
    }

    public function participantes(): BelongsToMany
    {
        return $this->belongsToMany(Participante::class, 'participante_requisito', 'requisito_id', 'participante_id')
            ->using(ParticipanteRequisito::class)
            ->withPivot('estado', 'ruta_evidencia', 'revisado_por', 'fecha_validacion');
    }

}
