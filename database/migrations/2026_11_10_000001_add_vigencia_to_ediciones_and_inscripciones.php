<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nulo significa política pendiente; no cambia el acceso de filas anteriores.
        Schema::table('ediciones', function (Blueprint $table): void {
            $table->string('tipo_vigencia', 25)->nullable();
            $table->unsignedSmallInteger('duracion_acceso_dias')->nullable();
            $table->unsignedSmallInteger('prorroga_gratuita_dias')->nullable();
        });

        Schema::table('inscripciones', function (Blueprint $table): void {
            $table->timestamp('acceso_inicia_at')->nullable();
            $table->timestamp('acceso_vence_at')->nullable();
            $table->unsignedSmallInteger('prorroga_gratuita_dias')->nullable();
            $table->timestamp('prorroga_gratuita_aplicada_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inscripciones', function (Blueprint $table): void {
            $table->dropColumn(['acceso_inicia_at', 'acceso_vence_at', 'prorroga_gratuita_dias', 'prorroga_gratuita_aplicada_at']);
        });
        Schema::table('ediciones', function (Blueprint $table): void {
            $table->dropColumn(['tipo_vigencia', 'duracion_acceso_dias', 'prorroga_gratuita_dias']);
        });
    }
};
