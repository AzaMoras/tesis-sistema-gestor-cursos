<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('entregas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('actividad_id')->constrained('actividades')->restrictOnDelete();
        $table->foreignId('inscripcion_id')->constrained('inscripciones')->restrictOnDelete();
        $table->unsignedSmallInteger('numero_intento')->default(1);
        $table->string('ruta_evidencia', 500)->nullable();
        $table->json('respuestas_json')->nullable();
        $table->timestamp('fecha_entrega');
        $table->string('estado', 25)->default('enviada');
        $table->timestamps();
        $table->unique(['actividad_id', 'inscripcion_id', 'numero_intento'], 'entrega_act_ins_int_uq');
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
