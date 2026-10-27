<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class GrupoInstructor extends Pivot
{
    protected $table = 'grupo_instructor';

    public $timestamps = false;

    protected $foreignKey = 'grupo_id';

    protected $relatedKey = 'instructor_id';

    protected $fillable = [];

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['fecha_asignacion' => 'datetime'];
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }
}
