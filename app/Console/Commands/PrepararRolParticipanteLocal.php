<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\VerificaPreparacionLocal;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\User;
use App\Models\UsuarioRol;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PrepararRolParticipanteLocal extends Command
{
    use VerificaPreparacionLocal;

    protected $signature = 'aula-verde:preparar-rol-participante
        {--base= : Nombre exacto de la base local prevista}
        {--host=127.0.0.1 : Host local previsto}
        {--puerto=3306 : Puerto MariaDB previsto}
        {--usuario= : ID explícito del administrador existente que autoriza la preparación}
        {--respaldo= : Respaldo privado comprobado en una restauración independiente}
        {--sha256= : SHA-256 previamente calculado del respaldo}
        {--autorizar-pruebas : Solo testing SQLite :memory:}';

    protected $description = 'Prepara solo el rol global participante en desarrollo local; no asigna usuarios ni permisos';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) || app()->configurationIsCached()
            || ! $this->input->isInteractive() || $this->option('quiet') || $this->option('silent')
            || $this->output->isQuiet() || $this->output->isSilent()) {
            $this->error('Requiere entorno local o testing autorizado, sin caché y ejecución interactiva visible. Producción está excluida.');

            return self::FAILURE;
        }
        try {
            if (! $this->destino() || ! ($sha = $this->comprobarRespaldo())) {
                return self::FAILURE;
            }
            $id = filter_var($this->option('usuario'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) {
                $this->error('Indica --usuario con el ID explícito de un administrador existente.');

                return self::FAILURE;
            }
            $usuario = $this->operador($id);
            $this->table(['ID', 'Nombre', 'Correo'], [[$usuario->id, $usuario->name, $usuario->email]]);
            $this->line('Escrituras: crear roles.clave=participante si falta y registrar esa creación en bitacora.');
            $this->line('No asigna usuarios, instituciones ni permisos; no altera un rol existente, contraseñas o verificación.');
            if (! $this->confirm('¿Confirmas que el respaldo corresponde a esta base y comprobaste su restauración en un entorno separado?', false)
                || ! $this->confirm('¿Autorizas preparar únicamente el rol global participante?', false)) {
                $this->warn('Cancelado sin escrituras.');

                return self::FAILURE;
            }
            DB::transaction(function () use ($usuario, $sha): void {
                $actual = $this->operador($usuario->id, true);
                if ($actual->email !== $usuario->email || $actual->name !== $usuario->name) {
                    throw ValidationException::withMessages(['usuario' => 'La identidad del operador cambió; repite la revisión.']);
                }
                if (Rol::query()->where('clave', 'participante')->lockForUpdate()->exists()) {
                    return;
                }
                $rol = new Rol;
                $rol->forceFill(['clave' => 'participante', 'nombre' => 'Participante'])->save();
                $bitacora = new Bitacora;
                $bitacora->forceFill(['institucion_id' => null, 'usuario_id' => $actual->id, 'accion' => 'roles.preparar_participante_local',
                    'entidad' => 'roles', 'registro_id' => $rol->id, 'fecha_accion' => now(),
                    'detalles_json' => ['origen' => 'artisan_local', 'respaldo_sha256' => $sha]])->save();
            }, 3);
            $this->info('Rol participante preparado o reutilizado. No se asignaron privilegios a ninguna cuenta.');

            return self::SUCCESS;
        } catch (ValidationException $error) {
            foreach ($error->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->error($mensaje);
                }
            }
        } catch (Throwable) {
            $this->error('No se completó la preparación. La transacción revierte sus escrituras; revisa conexión y esquema sin publicar secretos.');
        }

        return self::FAILURE;
    }

    private function operador(int $id, bool $bloquear = false): User
    {
        $usuario = User::query()->where('estado_cuenta', 'activa')->when($bloquear, fn ($q) => $q->lockForUpdate())->find($id);
        $administrador = $usuario && UsuarioRol::query()->where('user_id', $id)
            ->whereHas('rol', fn ($q) => $q->where('clave', 'administrador'))
            ->whereHas('institucion', fn ($q) => $q->where('estado', 'activa'))->exists();
        if (! $administrador) {
            throw ValidationException::withMessages(['usuario' => 'El operador debe ser una cuenta activa con rol administrador en una institución activa.']);
        }

        return $usuario;
    }
}
