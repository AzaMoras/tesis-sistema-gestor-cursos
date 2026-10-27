<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('constancias', function (Blueprint $table) {
        $table->id();
        $table->foreignId('inscripcion_id')->unique()->constrained('inscripciones')->restrictOnDelete();
        $table->string('folio', 100)->unique();
        $table->string('codigo_verificacion', 100)->unique();
        $table->decimal('calificacion_final', 5, 2);
        $table->decimal('porcentaje_asistencia', 5, 2);
        $table->string('ruta_pdf', 500);
        $table->timestamp('fecha_emision');
        $table->string('estado', 25)->default('emitida');
        $table->timestamps();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('constancias');
    }
};
