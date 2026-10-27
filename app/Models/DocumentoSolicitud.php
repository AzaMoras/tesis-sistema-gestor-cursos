<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoSolicitud extends Model
{
    protected $table = 'documentos_solicitud';

    protected $fillable = [
        'tipo_documento',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(SolicitudRegistro::class, 'solicitud_registro_id');
    }

}
