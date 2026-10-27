<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participante extends Model
{
    use SoftDeletes;

    protected $table = 'participantes';

    protected $fillable = [
        'telefono',
    ];

    protected function casts(): array
    {
        return [
            'fecha_alta' => 'date',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'participante_id');
    }

    public function esperas(): HasMany
    {
        return $this->hasMany(ListaEspera::class, 'participante_id');
    }

    public function requisitos(): BelongsToMany
    {
        return $this->belongsToMany(Requisito::class, 'participante_requisito', 'participante_id', 'requisito_id')
            ->using(ParticipanteRequisito::class)
            ->withPivot('estado', 'ruta_evidencia', 'revisado_por', 'fecha_validacion');
    }

}
