<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ServiceTestCase;

class VigenciaMigrationTest extends ServiceTestCase
{
    public function test_additive_migration_and_rollback_preserve_existing_records(): void
    {
        $enrollment = $this->enrollment();
        $submission = $this->submission($enrollment);
        $migration = require database_path('migrations/2026_11_10_000001_add_vigencia_to_ediciones_and_inscripciones.php');
        $migration->down();
        $before = (array) DB::table('inscripciones')->find($enrollment->id);
        try {
            $migration->up();
            $after = (array) DB::table('inscripciones')->find($enrollment->id);
            self::assertSame($before, array_intersect_key($after, $before));
            self::assertNull($after['acceso_inicia_at']);
            self::assertNull($after['acceso_vence_at']);
            self::assertNull(DB::table('ediciones')->where('id', $this->grupo->edicion_id)->value('tipo_vigencia'));
            $migration->down();
            self::assertFalse(Schema::hasColumn('inscripciones', 'acceso_vence_at'));
            self::assertFalse(Schema::hasColumn('ediciones', 'tipo_vigencia'));
            self::assertSame($before, (array) DB::table('inscripciones')->find($enrollment->id));
            $this->assertDatabaseHas('entregas', ['id' => $submission->id]);
        } finally {
            if (! Schema::hasColumn('ediciones', 'tipo_vigencia')) {
                $migration->up();
            }
        }
    }
}
