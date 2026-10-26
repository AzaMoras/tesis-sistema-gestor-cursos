<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('participante_requisito', function (Blueprint $table) {
        $table->foreignId('participante_id')->constrained('participantes')->restrictOnDelete();
        $table->foreignId('requisito_id')->constrained('requisitos')->restrictOnDelete();
        $table->string('estado', 25)->default('pendiente');
        $table->string('ruta_evidencia', 500)->nullable();
        $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('fecha_validacion')->nullable();
        $table->primary(['participante_id', 'requisito_id'], 'part_req_pk');
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('participante_requisito');
    }
};
