<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('bitacora', function (Blueprint $table) {
        $table->id();
        $table->foreignId('institucion_id')->nullable()->constrained('instituciones')->nullOnDelete();
        $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
        $table->string('accion', 120);
        $table->string('entidad', 100);
        $table->unsignedBigInteger('registro_id')->nullable();
        $table->json('detalles_json')->nullable();
        $table->timestamp('fecha_accion')->useCurrent();
        $table->index(['entidad', 'registro_id']);
        $table->index('fecha_accion');
        // Historial de auditoría: no eliminar mediante FK en cascada.
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
