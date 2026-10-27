<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('documentos_solicitud', function (Blueprint $table) {
        $table->id();
        $table->foreignId('solicitud_registro_id')->constrained('solicitudes_registro')->restrictOnDelete();
        $table->string('tipo_documento', 60);
        $table->string('ruta_archivo', 500);
        $table->string('nombre_original', 255);
        $table->string('tipo_mime', 100);
        $table->unsignedBigInteger('tamano_bytes');
        $table->string('estado_revision', 25)->default('pendiente');
        $table->timestamps();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_solicitud');
    }
};
