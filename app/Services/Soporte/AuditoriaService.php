<?php

namespace App\Services\Soporte;

use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use LogicException;

final class AuditoriaService
{
    /** Se ejecuta dentro de la misma transacción de la operación auditada. */
    public function registrar(?User $actor, int $institucionId, string $accion, Model $registro, array $detalles = []): void
    {
        if ($registro->getConnection()->transactionLevel() === 0 || ! $registro->exists) {
            throw new LogicException('La auditoría requiere un registro persistido dentro de una transacción.');
        }

        $bitacora = new Bitacora;
        $bitacora->setConnection($registro->getConnectionName());
        $bitacora->institucion_id = $institucionId;
        $bitacora->usuario_id = $actor?->getKey();
        $bitacora->accion = $accion;
        $bitacora->entidad = $registro->getTable();
        $bitacora->registro_id = $registro->getKey();
        $bitacora->detalles_json = $detalles;
        $bitacora->fecha_accion = now();
        $bitacora->save();
    }
}
