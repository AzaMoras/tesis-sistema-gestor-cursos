<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('sesiones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('grupo_id')->constrained('grupos')->restrictOnDelete();
        $table->string('titulo', 180);
        $table->timestamp('inicio_at');
        $table->timestamp('fin_at');
        $table->string('enlace_virtual', 500)->nullable();
        $table->string('estado', 25)->default('programada');
        $table->timestamps();
        $table->index(['grupo_id', 'inicio_at']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesiones');
    }
};
