<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('solicitudes_registro', function (Blueprint $table) {
        $table->id();
        $table->foreignId('institucion_id')->constrained('instituciones')->restrictOnDelete();
        $table->string('nombre', 120);
        $table->string('apellidos', 160);
        $table->string('correo', 190)->index();
        $table->string('estado', 25)->default('pendiente')->index();
        $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('usuario_vinculado_id')->nullable()->constrained('users')->nullOnDelete();
        $table->text('motivo_revision')->nullable();
        $table->timestamp('fecha_revision')->nullable();
        $table->timestamps();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_registro');
    }
};
