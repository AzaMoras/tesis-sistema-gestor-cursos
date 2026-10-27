<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialCurso extends Model
{
    use SoftDeletes;

    protected $table = 'materiales_curso';

    protected $fillable = [
        'titulo',
        'tipo',
        'url_externa',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'publicado' => 'boolean',
        ];
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(ModuloCurso::class, 'modulo_id');
    }

}
