<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\User;
use App\Models\UsuarioRol;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\Support\InscripcionPublicaTestCase;

class PrepararRolParticipanteLocalTest extends InscripcionPublicaTestCase
{
    private string $respaldoPrueba;

    private User $operador;

    private const RESPALDO = '¿Confirmas que el respaldo corresponde a esta base y comprobaste su restauración en un entorno separado?';

    private const PREPARAR = '¿Autorizas preparar únicamente el rol global participante?';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rol->delete();
        $rol = $this->record(Rol::class, ['clave' => 'administrador', 'nombre' => 'Administrador']);
        $this->operador = User::factory()->unverified()->create(['estado_cuenta' => 'activa']);
        $this->record(UsuarioRol::class, ['user_id' => $this->operador->id, 'rol_id' => $rol->id, 'institucion_id' => $this->institucion->id]);
        $this->respaldoPrueba = sys_get_temp_dir().'/av-rol-fixture-'.bin2hex(random_bytes(12)).'.sql';
        file_put_contents($this->respaldoPrueba, '-- Fixture temporal de checksum; no es un respaldo real.');
    }

    protected function tearDown(): void
    {
        if (isset($this->respaldoPrueba) && is_file($this->respaldoPrueba)) {
            unlink($this->respaldoPrueba);
        }
        parent::tearDown();
    }

    private function opciones(): array
    {
        return ['--base' => ':memory:', '--autorizar-pruebas' => true, '--usuario' => $this->operador->id,
            '--respaldo' => $this->respaldoPrueba, '--sha256' => hash_file('sha256', $this->respaldoPrueba)];
    }

    public function test_prepara_idempotentemente_sin_asignar_roles_o_permisos_y_preserva_cuentas(): void
    {
        $atributos = $this->operador->fresh()->getAttributes();
        for ($i = 0; $i < 2; $i++) {
            $this->artisan('aula-verde:preparar-rol-participante', $this->opciones())
                ->expectsOutputToContain('Base seleccionada: :memory:')
                ->expectsConfirmation(self::RESPALDO, 'yes')->expectsConfirmation(self::PREPARAR, 'yes')->assertSuccessful();
        }
        self::assertSame(1, Rol::where('clave', 'participante')->count());
        $this->assertDatabaseCount('usuario_rol', 1);
        $this->assertDatabaseCount('permisos', 0);
        $this->assertDatabaseCount('rol_permiso', 0);
        $this->assertDatabaseCount('inscripciones', 0);
        $this->assertDatabaseCount('bitacora', 1);
        self::assertSame($atributos, $this->operador->fresh()->getAttributes());
        $this->assertDatabaseHas('bitacora', ['accion' => 'roles.preparar_participante_local', 'usuario_id' => $this->operador->id, 'institucion_id' => null]);
    }

    public function test_rechaza_produccion_y_ejecucion_no_interactiva(): void
    {
        $this->artisan('aula-verde:preparar-rol-participante', $this->opciones() + ['--no-interaction' => true])->assertFailed();
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('aula-verde:preparar-rol-participante', $this->opciones())->assertFailed();
        self::assertFalse(Rol::where('clave', 'participante')->exists());
    }

    public function test_rechaza_operador_sin_rol_inactivo_y_sin_seleccion_explicita(): void
    {
        foreach ([['--usuario' => $this->usuario->id], ['--usuario' => null], ['--usuario' => 99999]] as $opciones) {
            $this->artisan('aula-verde:preparar-rol-participante', array_replace($this->opciones(), $opciones))->assertFailed();
        }
        $this->operador->forceFill(['estado_cuenta' => 'inactiva'])->save();
        $this->artisan('aula-verde:preparar-rol-participante', $this->opciones())->assertFailed();
        self::assertFalse(Rol::where('clave', 'participante')->exists());
    }

    public function test_rechaza_base_respaldo_sha_y_testing_sin_autorizacion(): void
    {
        foreach ([['--base' => 'otra'], ['--sha256' => str_repeat('0', 64)], ['--respaldo' => 'ausente.sql'], ['--autorizar-pruebas' => false]] as $opciones) {
            $this->artisan('aula-verde:preparar-rol-participante', array_replace($this->opciones(), $opciones))->assertFailed();
        }
        self::assertFalse(Rol::where('clave', 'participante')->exists());
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_cancelar_cualquiera_de_las_confirmaciones_no_escribe(): void
    {
        $this->artisan('aula-verde:preparar-rol-participante', $this->opciones())->expectsConfirmation(self::RESPALDO, 'no')->assertFailed();
        $this->artisan('aula-verde:preparar-rol-participante', $this->opciones())
            ->expectsConfirmation(self::RESPALDO, 'yes')->expectsConfirmation(self::PREPARAR, 'no')->assertFailed();
        self::assertFalse(Rol::where('clave', 'participante')->exists());
        $this->assertDatabaseCount('bitacora', 0);
    }

    public function test_fallo_de_auditoria_revierte_el_rol(): void
    {
        Bitacora::creating(fn () => throw new RuntimeException('Fallo simulado'));
        try {
            $this->artisan('aula-verde:preparar-rol-participante', $this->opciones())
                ->expectsConfirmation(self::RESPALDO, 'yes')->expectsConfirmation(self::PREPARAR, 'yes')->assertFailed();
        } finally {
            Event::forget('eloquent.creating: '.Bitacora::class);
        }
        self::assertFalse(Rol::where('clave', 'participante')->exists());
        $this->assertDatabaseCount('bitacora', 0);
    }
}
