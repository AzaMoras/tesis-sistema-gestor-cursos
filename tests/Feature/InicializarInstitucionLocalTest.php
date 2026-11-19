<?php

namespace Tests\Feature;

use App\Models\Institucion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use App\Services\InicializacionInstitucionalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InicializarInstitucionLocalTest extends TestCase
{
    use RefreshDatabase;

    private string $respaldo;

    private const RESPALDO = '¿Confirmas que el respaldo corresponde a esta base y comprobaste su restauración en un entorno separado?';

    private const PRIVILEGIOS = '¿Autorizas asignar los privilegios administrativos indicados a la cuenta mostrada?';

    protected function setUp(): void
    {
        parent::setUp();
        $this->respaldo = sys_get_temp_dir().'/aula-verde-init-'.bin2hex(random_bytes(12)).'.sql';
        file_put_contents($this->respaldo, '-- Archivo ficticio exclusivo del test de checksum; no es un respaldo real.');
    }

    protected function tearDown(): void
    {
        if (isset($this->respaldo) && is_file($this->respaldo)) {
            unlink($this->respaldo);
        }
        parent::tearDown();
    }

    private function opciones(?User $usuario = null): array
    {
        return [
            '--base' => ':memory:', '--autorizar-pruebas' => true,
            '--usuario' => $usuario?->id,
            '--respaldo' => $this->respaldo, '--sha256' => hash_file('sha256', $this->respaldo),
        ];
    }

    private function sinEscrituras(): void
    {
        foreach (['instituciones', 'roles', 'permisos', 'rol_permiso', 'usuario_rol', 'bitacora'] as $tabla) {
            $this->assertDatabaseCount($tabla, 0);
        }
    }

    public function test_inicializa_cuenta_elegida_preserva_credenciales_y_permite_gestion(): void
    {
        $otro = User::factory()->create(['estado_cuenta' => 'activa']);
        $usuario = User::factory()->create(['estado_cuenta' => 'activa', 'email_verified_at' => null]);
        $atributos = $usuario->fresh()->getAttributes();
        $this->artisan('aula-verde:inicializar-institucion', $this->opciones($usuario))
            ->expectsOutputToContain('Base seleccionada: :memory:')
            ->expectsConfirmation(self::RESPALDO, 'yes')->expectsConfirmation(self::PRIVILEGIOS, 'yes')->assertSuccessful();
        $institucion = Institucion::sole();
        self::assertSame(InicializacionInstitucionalService::SLUG, $institucion->slug);
        self::assertSame('activa', $institucion->estado);
        self::assertSame($atributos, $usuario->fresh()->getAttributes());
        self::assertTrue($usuario->rolesEnInstitucion($institucion->id)->where('roles.clave', 'administrador')->exists());
        self::assertTrue($usuario->permisosEnInstitucion($institucion->id)->where('permisos.clave', 'cursos.gestionar')->exists());
        self::assertFalse($otro->roles()->exists());
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('bitacora', 1);
        $this->assertDatabaseHas('usuario_rol', ['user_id' => $usuario->id, 'institucion_id' => $institucion->id, 'asignado_por' => null]);
        $this->actingAs($usuario)->get('/gestion')->assertOk()->assertSee($institucion->nombre);
        $this->get('/gestion/'.$institucion->id.'/cursos')->assertOk();
        $this->actingAs($otro)->get('/gestion/'.$institucion->id.'/cursos')->assertForbidden();
    }

    public function test_repetir_no_duplica_ni_sobrescribe_registros(): void
    {
        $usuario = User::factory()->create(['estado_cuenta' => 'activa']);
        for ($i = 0; $i < 2; $i++) {
            $this->artisan('aula-verde:inicializar-institucion', $this->opciones($usuario))
                ->expectsConfirmation(self::RESPALDO, 'yes')->expectsConfirmation(self::PRIVILEGIOS, 'yes')->assertSuccessful();
        }
        foreach (['instituciones', 'roles', 'permisos', 'rol_permiso', 'usuario_rol', 'bitacora', 'users'] as $tabla) {
            $this->assertDatabaseCount($tabla, 1);
        }
    }

    public function test_no_adivina_usuario_y_puede_pedir_identificacion_explicita(): void
    {
        $usuario = User::factory()->create(['estado_cuenta' => 'activa']);
        $opciones = $this->opciones();
        unset($opciones['--usuario']);
        $this->artisan('aula-verde:inicializar-institucion', $opciones)
            ->expectsQuestion('ID de la cuenta existente que autorizas como administrador', (string) $usuario->id)
            ->expectsConfirmation(self::RESPALDO, 'yes')->expectsConfirmation(self::PRIVILEGIOS, 'yes')->assertSuccessful();
        $this->assertDatabaseHas('usuario_rol', ['user_id' => $usuario->id]);
    }

    public function test_rechaza_cuentas_inactivas_eliminadas_e_inexistentes(): void
    {
        foreach (['pendiente', 'suspendida'] as $estado) {
            $usuario = User::factory()->create(['estado_cuenta' => $estado]);
            $this->artisan('aula-verde:inicializar-institucion', $this->opciones($usuario))->assertFailed();
        }
        $eliminado = User::factory()->create(['estado_cuenta' => 'activa']);
        $eliminado->delete();
        $this->artisan('aula-verde:inicializar-institucion', $this->opciones($eliminado))->assertFailed();
        $this->artisan('aula-verde:inicializar-institucion', array_replace($this->opciones(), ['--usuario' => 99999]))->assertFailed();
        $this->sinEscrituras();
    }

    public function test_cancelacion_de_cualquiera_de_las_confirmaciones_no_escribe(): void
    {
        $usuario = User::factory()->create(['estado_cuenta' => 'activa']);
        $this->artisan('aula-verde:inicializar-institucion', $this->opciones($usuario))->expectsConfirmation(self::RESPALDO, 'no')->assertFailed();
        $this->artisan('aula-verde:inicializar-institucion', $this->opciones($usuario))
            ->expectsConfirmation(self::RESPALDO, 'yes')->expectsConfirmation(self::PRIVILEGIOS, 'no')->assertFailed();
        $this->sinEscrituras();
    }

    public function test_rechaza_testing_sin_autorizacion_base_distinta_y_respaldo_invalido(): void
    {
        $usuario = User::factory()->create(['estado_cuenta' => 'activa']);
        foreach ([['--autorizar-pruebas' => false], ['--base' => 'otra'], ['--base' => ''], ['--respaldo' => 'no-existe.sql'], ['--sha256' => str_repeat('0', 64)], ['--usuario' => 'incorrecto']] as $cambio) {
            $this->artisan('aula-verde:inicializar-institucion', array_replace($this->opciones($usuario), $cambio))->assertFailed();
        }
        file_put_contents($this->respaldo, '');
        $this->artisan('aula-verde:inicializar-institucion', $this->opciones($usuario))->assertFailed();
        $this->sinEscrituras();
    }

    public function test_rechaza_produccion_aunque_se_autoricen_pruebas(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('aula-verde:inicializar-institucion', $this->opciones())->assertFailed();
        $this->sinEscrituras();
    }

    public function test_local_rechaza_sqlite_incluso_con_flag_de_pruebas(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->artisan('aula-verde:inicializar-institucion', $this->opciones())->assertFailed();
        $this->sinEscrituras();
    }

    public function test_no_adopta_institucion_ajena_o_inactiva_ni_la_reactiva(): void
    {
        $usuario = User::factory()->create(['estado_cuenta' => 'activa']);
        $institucion = new Institucion;
        $institucion->forceFill(['slug' => InicializacionInstitucionalService::SLUG, 'nombre' => 'Otra institución', 'estado' => 'inactiva'])->save();
        $this->artisan('aula-verde:inicializar-institucion', $this->opciones($usuario))
            ->expectsConfirmation(self::RESPALDO, 'yes')->expectsConfirmation(self::PRIVILEGIOS, 'yes')->assertFailed();
        self::assertSame('inactiva', $institucion->fresh()->estado);
        self::assertSame('Otra institución', $institucion->fresh()->nombre);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_fallo_en_auditoria_revierte_todas_las_escrituras(): void
    {
        $usuario = User::factory()->create(['estado_cuenta' => 'activa']);
        // AuditoriaService es final: se provoca una excepción real en su INSERT.
        DB::statement("CREATE TRIGGER impedir_bitacora BEFORE INSERT ON bitacora BEGIN SELECT RAISE(ABORT, 'Fallo de auditoría de prueba'); END");
        $this->artisan('aula-verde:inicializar-institucion', $this->opciones($usuario))
            ->expectsConfirmation(self::RESPALDO, 'yes')->expectsConfirmation(self::PRIVILEGIOS, 'yes')->assertFailed();
        $this->sinEscrituras();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_reutiliza_rol_y_permiso_existentes_sin_cambiar_sus_atributos(): void
    {
        $usuario = User::factory()->create(['estado_cuenta' => 'activa']);
        $rol = new Rol;
        $rol->forceFill(['clave' => 'administrador', 'nombre' => 'Nombre conservado'])->save();
        $permiso = new Permiso;
        $permiso->forceFill(['clave' => 'cursos.gestionar', 'descripcion' => 'Descripción conservada'])->save();
        $this->artisan('aula-verde:inicializar-institucion', $this->opciones($usuario))
            ->expectsConfirmation(self::RESPALDO, 'yes')->expectsConfirmation(self::PRIVILEGIOS, 'yes')->assertSuccessful();
        self::assertSame('Nombre conservado', $rol->fresh()->nombre);
        self::assertSame('Descripción conservada', $permiso->fresh()->descripcion);
        $this->assertDatabaseCount('roles', 1);
        $this->assertDatabaseCount('permisos', 1);
    }

    public function test_no_interaction_no_omite_las_confirmaciones(): void
    {
        $usuario = User::factory()->create(['estado_cuenta' => 'activa']);
        $this->artisan('aula-verde:inicializar-institucion', $this->opciones($usuario) + ['--no-interaction' => true])->assertFailed();
        $this->sinEscrituras();
    }

    public function test_no_admite_ocultar_la_base_y_la_identidad_mostradas(): void
    {
        $usuario = User::factory()->create(['estado_cuenta' => 'activa']);
        foreach (['--quiet', '--silent'] as $opcion) {
            $this->artisan('aula-verde:inicializar-institucion', $this->opciones($usuario) + [$opcion => true])->assertFailed();
        }
        $this->sinEscrituras();
    }

    public function test_una_cuenta_que_cambia_despues_de_confirmarse_no_recibe_privilegios(): void
    {
        $usuario = User::factory()->create(['estado_cuenta' => 'activa']);
        $correo = $usuario->email;
        $usuario->forceFill(['email' => 'cambio@example.test'])->save();
        $this->expectException(ValidationException::class);
        try {
            app(InicializacionInstitucionalService::class)->inicializar($usuario->id, $correo, $usuario->name, hash_file('sha256', $this->respaldo));
        } finally {
            $this->sinEscrituras();
        }
    }
}
