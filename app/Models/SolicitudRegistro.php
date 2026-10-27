<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SolicitudRegistro extends Model
{
    protected $table = 'solicitudes_registro';

    protected $fillable = [
        'nombre',
        'apellidos',
        'correo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_revision' => 'datetime',
        ];
    }

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function usuarioVinculado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_vinculado_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoSolicitud::class, 'solicitud_registro_id');
    }

}
