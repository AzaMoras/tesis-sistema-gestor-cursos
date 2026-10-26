<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('lista_espera', function (Blueprint $table) {
        $table->id();
        $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete();
        $table->foreignId('participante_id')->constrained('participantes')->restrictOnDelete();
        $table->unsignedInteger('posicion');
        $table->string('estado', 25)->default('esperando');
        $table->timestamp('fecha_solicitud')->useCurrent();
        $table->timestamp('oferta_enviada_at')->nullable();
        $table->timestamp('oferta_vence_at')->nullable();
        $table->timestamps();
        $table->unique(['grupo_id', 'participante_id'], 'espera_grupo_part_uq');
        $table->unique(['grupo_id', 'posicion'], 'espera_grupo_pos_uq');
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_espera');
    }
};
