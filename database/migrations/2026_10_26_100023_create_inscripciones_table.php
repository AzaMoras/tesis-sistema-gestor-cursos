<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('inscripciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('participante_id')->constrained('participantes')->restrictOnDelete();
        $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete();
        $table->string('estado', 30)->default('pendiente_pago');
        $table->decimal('precio_acordado', 12, 2)->default(0);
        $table->timestamp('reserva_vence_at')->nullable();
        $table->timestamp('fecha_inscripcion')->nullable();
        $table->timestamp('fecha_cancelacion')->nullable();
        $table->timestamps();
        $table->unique(['participante_id', 'grupo_id'], 'insc_part_grupo_uq');
        $table->index(['grupo_id', 'estado']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
