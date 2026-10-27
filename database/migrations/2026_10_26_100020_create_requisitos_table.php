<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('requisitos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('institucion_id')->constrained('instituciones')->restrictOnDelete();
        $table->string('nombre', 160);
        $table->text('descripcion')->nullable();
        $table->boolean('requiere_documento')->default(false);
        $table->string('estado', 25)->default('activo');
        $table->timestamps();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitos');
    }
};
