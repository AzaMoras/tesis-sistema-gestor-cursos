<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('inactivos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('institucion_id')->nullable()->constrained('instituciones')->nullOnDelete();
        $table->string('entidad', 100);
        $table->unsignedBigInteger('registro_id');
        $table->text('motivo');
        $table->foreignId('desactivado_por')->constrained('users')->restrictOnDelete();
        $table->timestamp('desactivado_en')->useCurrent();
        $table->foreignId('restaurado_por')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamp('restaurado_en')->nullable();
        $table->timestamps();
        $table->index(['entidad', 'registro_id']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('inactivos');
    }
};
