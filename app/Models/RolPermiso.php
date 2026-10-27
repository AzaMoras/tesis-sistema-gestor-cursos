<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RolPermiso extends Pivot
{
    protected $table = 'rol_permiso';

    public $timestamps = false;

    protected $foreignKey = 'rol_id';

    protected $relatedKey = 'permiso_id';

    // La concesión de permisos requiere atributos explícitos y autorización.
    protected $fillable = [];

    protected $guarded = ['*'];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function permiso(): BelongsTo
    {
        return $this->belongsTo(Permiso::class, 'permiso_id');
    }
}
