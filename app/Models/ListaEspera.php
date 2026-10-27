<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListaEspera extends Model
{
    protected $table = 'lista_espera';

    // La posición y las ofertas se asignan explícitamente.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
            'fecha_solicitud' => 'datetime',
            'oferta_enviada_at' => 'datetime',
            'oferta_vence_at' => 'datetime',
        ];
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function participante(): BelongsTo
    {
        return $this->belongsTo(Participante::class, 'participante_id');
    }

}
