<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('asistencias', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sesion_id')->constrained('sesiones')->restrictOnDelete();
        $table->foreignId('inscripcion_id')->constrained('inscripciones')->restrictOnDelete();
        $table->string('estado', 25);
        $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('fecha_registro')->useCurrent();
        $table->timestamps();
        $table->unique(['sesion_id', 'inscripcion_id'], 'asis_sesion_insc_uq');
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
