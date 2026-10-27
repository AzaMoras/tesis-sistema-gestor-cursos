<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('grupo_instructor', function (Blueprint $table) {
        $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete();
        $table->foreignId('instructor_id')->constrained('instructores')->restrictOnDelete();
        $table->string('funcion', 40)->default('principal');
        $table->timestamp('fecha_asignacion')->useCurrent();
        $table->primary(['grupo_id', 'instructor_id']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_instructor');
    }
};
