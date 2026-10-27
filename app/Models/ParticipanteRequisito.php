<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ParticipanteRequisito extends Pivot
{
    protected $table = 'participante_requisito';

    public $timestamps = false;

    // Pivot usa el par de claves, no una columna id, al guardar desde la relación.
    protected $foreignKey = 'participante_id';

    protected $relatedKey = 'requisito_id';

    protected $fillable = [];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['fecha_validacion' => 'datetime'];
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'participante_id');
    }

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(Requisito::class, 'requisito_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
