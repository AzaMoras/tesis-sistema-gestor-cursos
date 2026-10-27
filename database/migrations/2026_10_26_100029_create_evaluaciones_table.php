<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('evaluaciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('entrega_id')->unique()->constrained('entregas')->restrictOnDelete();
        $table->foreignId('calificado_por')->nullable()->constrained('users')->nullOnDelete();
        $table->decimal('calificacion', 8, 2);
        $table->text('retroalimentacion')->nullable();
        $table->string('tipo_calificacion', 25)->default('manual');
        $table->timestamp('fecha_evaluacion');
        $table->timestamps();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones');
    }
};
