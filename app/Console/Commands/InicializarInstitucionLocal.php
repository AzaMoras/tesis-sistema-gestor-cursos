<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\VerificaPreparacionLocal;
use App\Models\Rol;
use App\Models\User;
use App\Services\InicializacionInstitucionalService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class InicializarInstitucionLocal extends Command
{
    use VerificaPreparacionLocal;

    protected $signature = 'aula-verde:inicializar-institucion
        {--base= : Nombre exacto de la base prevista}
        {--host=127.0.0.1 : Host local previsto}
        {--puerto=3306 : Puerto MariaDB previsto}
        {--usuario= : ID explícito de una cuenta existente}
        {--respaldo= : Archivo privado .sql, .sql.gz o .dump ya comprobado}
        {--sha256= : Checksum SHA-256 previamente calculado del respaldo}
        {--autorizar-pruebas : Solo para testing con SQLite :memory:}';

    protected $description = 'Inicializa una institución demostrativa y su administrador en MariaDB local, con confirmaciones';

    public function handle(InicializacionInstitucionalService $inicializacion): int
    {
        if (! app()->environment(['local', 'testing']) || app()->configurationIsCached()) {
            $this->error('Rechazado: solo local o testing autorizado, sin configuración cacheada. Producción está excluida.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive() || $this->option('quiet') || $this->option('silent')
            || $this->output->isQuiet() || $this->output->isSilent()) {
            $this->error('Se requiere ejecución interactiva con salida visible y confirmaciones del operador.');

            return self::FAILURE;
        }
        try {
            $conexion = $this->destino();
            if (! $conexion) {
                return self::FAILURE;
            }
            $sha256 = $this->comprobarRespaldo();
            if ($sha256 === null) {
                return self::FAILURE;
            }
            $id = $this->option('usuario') ?? $this->ask('ID de la cuenta existente que autorizas como administrador');
            if (! is_scalar($id) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $this->error('Identifica explícitamente una cuenta mediante un ID entero positivo.');

                return self::FAILURE;
            }
            $usuario = User::query()->find((int) $id);
            if (! $usuario || $usuario->estado_cuenta !== 'activa') {
                $this->error('La cuenta seleccionada no existe, fue eliminada o no está activa. No se activará automáticamente.');

                return self::FAILURE;
            }
            $this->table(['ID', 'Nombre', 'Correo', 'Estado'], [[$usuario->id, $usuario->name, $usuario->email, $usuario->estado_cuenta]]);
            $this->info('Institución: '.InicializacionInstitucionalService::NOMBRE.' / '.InicializacionInstitucionalService::SLUG);
            $asignaciones = Rol::where('clave', 'administrador')->first()?->usuarios()->count() ?? 0;
            $this->line('Escrituras previstas, únicamente cuando falten:');
            $this->line('1. instituciones: institución demostrativa activa.');
            $this->line('2. roles: administrador. 3. permisos: cursos.gestionar.');
            $this->line('4. rol_permiso: asociación GLOBAL del permiso al rol administrador.');
            $this->warn('Asignaciones actuales del rol administrador: '.$asignaciones.'. Todas recibirán ese permiso si todavía falta.');
            $this->line('5. usuario_rol: asignación a esta cuenta dentro de la institución demostrativa.');
            $this->line('6. bitacora: auditoría de los cambios, sin credenciales ni contenido del respaldo.');
            $this->line('No cambia contraseña, correo verificado, perfil de participante ni migraciones.');
            if (! $this->confirm('¿Confirmas que el respaldo corresponde a esta base y comprobaste su restauración en un entorno separado?', false)
                || ! $this->confirm('¿Autorizas asignar los privilegios administrativos indicados a la cuenta mostrada?', false)) {
                $this->warn('Cancelado sin escrituras.');

                return self::FAILURE;
            }
            $institucion = $inicializacion->inicializar($usuario->id, $usuario->email, $usuario->name, $sha256);
            $this->info('Inicialización completada. Institución ID: '.$institucion->id.'. Inicia sesión con la cuenta seleccionada y abre /gestion.');

            return self::SUCCESS;
        } catch (ValidationException $error) {
            foreach ($error->errors() as $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $this->error($mensaje);
                }
            }
        } catch (Throwable) {
            // Los mensajes de conexión/SQL pueden contener información sensible.
            $this->error('No se completó la inicialización. Revisa conexión/esquema; la transacción revierte cualquier escritura de este intento.');
        }

        return self::FAILURE;
    }
}
