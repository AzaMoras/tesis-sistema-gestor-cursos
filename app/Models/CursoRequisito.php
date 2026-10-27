<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CursoRequisito extends Pivot
{
    protected $table = 'curso_requisito';

    public $timestamps = false;

    protected $foreignKey = 'curso_id';

    protected $relatedKey = 'requisito_id';

    protected $fillable = [];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['obligatorio' => 'boolean'];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function requisito(): BelongsTo
    {
        return $this->belongsTo(Requisito::class, 'requisito_id');
    }
}
