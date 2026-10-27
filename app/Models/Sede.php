<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends Model
{
    use SoftDeletes;

    protected $table = 'sedes';

    protected $fillable = [
        'nombre',
        'proveedor',
        'region',
        'tipo',
    ];

    public function instituciones(): HasMany
    {
        return $this->hasMany(Institucion::class, 'sede_id');
    }

}
