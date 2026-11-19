<?php

namespace App\Console\Commands;

use App\Models\Institucion;
use App\Models\Rol;
use App\Models\User;
use App\Services\GestionOfertaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class PrepararDemoOferta extends Command
{
    protected $signature = 'aula-verde:demo-oferta {correo : Correo de la cuenta exclusiva de demostración}';

    protected $description = 'Prepara oferta DEMOSTRATIVA solo en SQLite local aula-verde-demo.sqlite';

    public function handle(GestionOfertaService $oferta): int
    {
        $conexion = config('database.connections.'.config('database.default'));
        if (! app()->environment('local') || app()->configurationIsCached()
            || ($conexion['driver'] ?? null) !== 'sqlite' || ! empty($conexion['url'])
            || realpath($conexion['database'] ?? '') !== realpath(database_path('aula-verde-demo.sqlite'))
            || ! is_file(database_path('aula-verde-demo.sqlite'))) {
            $this->error('Operación rechazada: requiere entorno local sin caché y SQLite database/aula-verde-demo.sqlite.');

            return self::FAILURE;
        }
        // Comprobar el driver efectivo antes de consultar o escribir datos.
        if (DB::connection()->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            return self::FAILURE;
        }
        $correo = mb_strtolower(trim($this->argument('correo')));
        Validator::make(['correo' => $correo], ['correo' => ['required', 'email', 'max:190']])->validate();
        $usuario = User::withTrashed()->where('email', $correo)->first();
        if ($usuario && ($usuario->trashed() || $usuario->name !== 'Personal de demostración' || $usuario->estado_cuenta !== 'activa')) {
            $this->error('Ese correo pertenece a otra cuenta. Usa una cuenta exclusiva de demostración.');

            return self::FAILURE;
        }
        $clave = null;
        if (! $usuario) {
            $clave = $this->secret('Define una contraseña exclusiva (no se mostrará ni guardará en archivos)');
            Validator::make(['password' => $clave], ['password' => ['required', 'string', 'max:72', Password::min(12)->mixedCase()->numbers()->symbols(), function ($campo, $valor, $fallar) {
                if (strlen($valor) > 72) {
                    $fallar('La contraseña admite hasta 72 bytes.');
                }
            }]])->validate();
        }
        $institucion = DB::transaction(function () use ($correo, $clave, $usuario, $oferta) {
            $institucion = Institucion::firstOrCreate(['slug' => 'aula-verde-demo'], ['nombre' => 'Institución DEMOSTRATIVA Aula Verde'])->refresh();
            if ($institucion->estado !== 'activa') {
                throw new \RuntimeException('La institución de demostración no está activa.');
            }
            if (! $usuario) {
                $usuario = new User;
                $usuario->forceFill(['name' => 'Personal de demostración', 'email' => $correo, 'password' => $clave, 'estado_cuenta' => 'activa'])->save();
            }
            $rol = Rol::where('clave', 'coordinador')->first();
            if (! $rol) {
                $rol = new Rol;
                $rol->forceFill(['clave' => 'coordinador', 'nombre' => 'Coordinador'])->save();
            }
            DB::table('permisos')->insertOrIgnore(['clave' => 'cursos.gestionar', 'descripcion' => 'Gestionar oferta institucional']);
            $permiso = DB::table('permisos')->where('clave', 'cursos.gestionar')->value('id');
            DB::table('rol_permiso')->insertOrIgnore(['rol_id' => $rol->id, 'permiso_id' => $permiso]);
            DB::table('usuario_rol')->insertOrIgnore(['user_id' => $usuario->id, 'rol_id' => $rol->id, 'institucion_id' => $institucion->id, 'created_at' => now(), 'updated_at' => now()]);
            if ($institucion->categorias()->where('slug', 'demostracion')->exists()) {
                return $institucion;
            }
            $categoria = $oferta->guardar($usuario, $institucion->id, 'categorias', null, ['nombre' => 'Categoría DEMOSTRATIVA', 'slug' => 'demostracion']);
            $curso = $oferta->guardar($usuario, $institucion->id, 'cursos', null, ['categoria_id' => $categoria->id, 'titulo' => 'Curso virtual gratuito DEMOSTRATIVO', 'descripcion' => 'Contenido de demostración, sin oferta institucional real.']);
            $edicion = $oferta->guardar($usuario, $institucion->id, 'ediciones', null, ['curso_id' => $curso->id, 'clave' => 'DEMO-01', 'modalidad' => 'autogestionado', 'fecha_inicio' => today()->addDay()->format('Y-m-d'), 'fecha_fin' => today()->addMonth()->format('Y-m-d'), 'precio' => '0.00', 'moneda' => 'MXN']);
            $grupo = $oferta->guardar($usuario, $institucion->id, 'grupos', null, ['edicion_id' => $edicion->id, 'codigo' => 'DEMO-G01', 'cupo_maximo' => 20]);
            foreach (['cursos' => $curso, 'ediciones' => $edicion, 'grupos' => $grupo] as $tipo => $registro) {
                $oferta->publicar($usuario, $institucion->id, $tipo, $registro->id);
            }

            return $institucion;
        });
        $this->info('Oferta DEMOSTRATIVA disponible. Accede con tu cuenta en /login y después en /gestion/'.$institucion->id.'/cursos.');

        return self::SUCCESS;
    }
}
