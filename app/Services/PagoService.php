<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\User;
use App\Services\Soporte\AuditoriaService;
use App\Services\Soporte\AutorizacionInstitucional;
use App\Services\Soporte\Importes;
use App\Services\Soporte\ReglasOperacion;
use DateTimeInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PagoService
{
    public function __construct(
        private readonly AutorizacionInstitucional $acceso,
        private readonly AuditoriaService $auditoria,
        private readonly ReglasOperacion $reglas,
    ) {}

    /** El UUID identifica el intento: debe reutilizarse al repetir la petición. */
    public function registrar(User $actor, int $institucionId, int $inscripcionId, string $referenciaInterna, array $datos): Pago
    {
        if (! isset($datos['monto']) || (! is_string($datos['monto']) && ! is_int($datos['monto']))) {
            throw ValidationException::withMessages(['monto' => 'Envía el importe como texto decimal, no como float.']);
        }
        $importe = Importes::centavos($datos['monto']);
        $datos = Validator::make($datos, [
            'metodo' => ['required', 'string', 'max:40'],
            'proveedor' => ['nullable', 'string', 'max:80'],
            'referencia_externa' => ['nullable', 'string', 'max:180'],
        ])->validate();
        Validator::make(['referencia_interna' => $referenciaInterna], ['referencia_interna' => ['required', 'uuid']])->validate();

        try {
            return DB::transaction(function () use ($actor, $institucionId, $inscripcionId, $referenciaInterna, $datos, $importe): Pago {
                $actor = $this->acceso->usuario($actor, $institucionId);
                $inscripcion = $this->acceso->inscripcion($institucionId, $inscripcionId, true);
                $participante = $this->acceso->participante($institucionId, $inscripcion->participante_id);
                $this->acceso->exigirPropietarioOPermiso($actor, $institucionId, $participante, 'pagos.registrar');
                $moneda = $inscripcion->grupo->edicion->moneda;
                $importeDecimal = Importes::decimal($importe);
                $existente = Pago::query()->where('referencia_interna', $referenciaInterna)->lockForUpdate()->first();
                if ($existente) {
                    if ((int) $existente->inscripcion_id !== $inscripcionId || $existente->monto !== $importeDecimal
                        || $existente->moneda !== $moneda || $existente->metodo !== $datos['metodo']
                        || $existente->proveedor !== ($datos['proveedor'] ?? null)
                        || $existente->referencia_externa !== ($datos['referencia_externa'] ?? null)) {
                        throw ValidationException::withMessages(['referencia_interna' => 'La referencia ya se utilizó con otros datos.']);
                    }

                    return $existente;
                }
                if ($inscripcion->estado !== $this->reglas->estado('inscripcion.pendiente_pago')
                    || $inscripcion->reserva_vence_at === null || $inscripcion->reserva_vence_at->lte(now())) {
                    throw ValidationException::withMessages(['inscripcion' => 'No hay una reserva de pago vigente para registrar un pago nuevo.']);
                }
                if ($importe <= 0 || $importe > Importes::centavos($inscripcion->precio_acordado) || ! preg_match('/\A[A-Z]{3}\z/', $moneda)) {
                    throw ValidationException::withMessages(['monto' => 'El importe o la moneda no son compatibles con la inscripción.']);
                }

                $pago = new Pago;
                $pago->inscripcion_id = $inscripcionId;
                $pago->referencia_interna = $referenciaInterna;
                $pago->referencia_externa = $datos['referencia_externa'] ?? null;
                $pago->monto = $importeDecimal;
                $pago->moneda = $moneda;
                $pago->metodo = $datos['metodo'];
                $pago->proveedor = $datos['proveedor'] ?? null;
                $pago->estado = $this->reglas->estado('pago.pendiente');
                $pago->monto_reembolsado = '0.00';
                $pago->save();
                $this->auditoria->registrar($actor, $institucionId, 'pagos.registrar', $pago);

                return $pago;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['referencia' => 'Una referencia de pago ya fue registrada. No se duplicó la operación.']);
        }
    }

    /** Acto explícito de personal autorizado, no comprobación de una pasarela. */
    public function verificarManual(User $actor, int $institucionId, int $pagoId, DateTimeInterface $fechaPago, string $evidenciaRevision): Pago
    {
        $datos = Validator::make(['evidencia_revision' => $evidenciaRevision], [
            'evidencia_revision' => ['required', 'string', 'max:4000'],
        ])->validate();
        $fecha = Carbon::instance($fechaPago)->copy();
        if ($fecha->gt(now())) {
            throw ValidationException::withMessages(['fecha_pago' => 'La fecha real del pago no puede estar en el futuro.']);
        }

        return DB::transaction(function () use ($actor, $institucionId, $pagoId, $fecha, $datos): Pago {
            $actor = $this->acceso->usuario($actor, $institucionId, 'pagos.verificar');
            [$pago, $inscripcion] = $this->pagoBloqueado($institucionId, $pagoId);
            $verificado = $this->reglas->estado('pago.verificado');
            if ($pago->estado === $verificado && $pago->verificado_por !== null && $pago->fecha_pago !== null) {
                if ($pago->fecha_pago->gt(now()) || $pago->moneda !== $inscripcion->grupo->edicion->moneda
                    || Importes::centavos($pago->monto) <= 0) {
                    throw ValidationException::withMessages(['pago' => 'El pago verificado contiene datos inconsistentes.']);
                }
                return $pago;
            }
            if ($pago->estado !== $this->reglas->estado('pago.pendiente') || $pago->moneda !== $inscripcion->grupo->edicion->moneda
                || Importes::centavos($pago->monto) <= 0 || Importes::centavos($pago->monto_reembolsado) !== 0) {
                throw ValidationException::withMessages(['pago' => 'El estado o los datos del pago no permiten la verificación manual.']);
            }

            $total = Importes::centavos($pago->monto);
            foreach ($inscripcion->pagos()->where('estado', $verificado)->whereKeyNot($pagoId)->lockForUpdate()->get() as $otro) {
                $monto = Importes::centavos($otro->monto);
                $reembolsado = Importes::centavos($otro->monto_reembolsado);
                if ($otro->verificado_por === null || $otro->fecha_pago === null || $otro->fecha_pago->gt(now())
                    || $otro->moneda !== $pago->moneda || $reembolsado > $monto || $total > PHP_INT_MAX - $monto) {
                    throw ValidationException::withMessages(['pago' => 'Hay otros pagos con datos de verificación inconsistentes.']);
                }
                $total += $monto - $reembolsado;
            }
            if ($total > Importes::centavos($inscripcion->precio_acordado)) {
                throw ValidationException::withMessages(['monto' => 'El total excede el precio acordado; falta definir el tratamiento de sobrepagos.']);
            }

            $pago->estado = $verificado;
            $pago->verificado_por = $actor->getKey();
            $pago->fecha_pago = $fecha;
            $pago->save();
            $this->auditoria->registrar($actor, $institucionId, 'pagos.verificar_manual', $pago, [
                'evidencia_revision' => $datos['evidencia_revision'],
            ]);

            // La confirmación de la inscripción es otra operación: un pago tardío
            // puede ser real sin que siga existiendo una reserva de lugar.
            return $pago;
        }, 3);
    }

    public function rechazar(User $actor, int $institucionId, int $pagoId, string $motivo): Pago
    {
        $datos = Validator::make(['motivo' => $motivo], ['motivo' => ['required', 'string', 'max:4000']])->validate();

        return DB::transaction(function () use ($actor, $institucionId, $pagoId, $datos): Pago {
            $actor = $this->acceso->usuario($actor, $institucionId, 'pagos.verificar');
            [$pago] = $this->pagoBloqueado($institucionId, $pagoId);
            $rechazado = $this->reglas->estado('pago.rechazado');
            if ($pago->estado === $rechazado) {
                return $pago;
            }
            if ($pago->estado !== $this->reglas->estado('pago.pendiente')) {
                throw ValidationException::withMessages(['pago' => 'Solo se puede rechazar un pago pendiente; no se simularán reembolsos.']);
            }
            $pago->estado = $rechazado;
            $pago->save();
            $this->auditoria->registrar($actor, $institucionId, 'pagos.rechazar', $pago, ['motivo' => $datos['motivo']]);

            return $pago;
        }, 3);
    }

    private function pagoBloqueado(int $institucionId, int $pagoId): array
    {
        $referencia = Pago::query()->whereHas('inscripcion.grupo.edicion.curso', fn ($query) => $query->where('institucion_id', $institucionId))
            ->findOrFail($pagoId);
        $inscripcion = $this->acceso->inscripcion($institucionId, $referencia->inscripcion_id, true);
        $pago = Pago::query()->where('inscripcion_id', $inscripcion->getKey())->lockForUpdate()->findOrFail($pagoId);

        return [$pago, $inscripcion];
    }
}
