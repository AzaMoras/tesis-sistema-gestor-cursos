<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('cursos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('institucion_id')->constrained('instituciones')->restrictOnDelete();
        $table->foreignId('categoria_id')->constrained('categorias_curso')->restrictOnDelete();
        $table->string('titulo', 180);
        $table->longText('descripcion');
        $table->text('objetivos')->nullable();
        $table->string('estado', 25)->default('borrador');
        $table->timestamps();
        $table->softDeletes();
        $table->index(['institucion_id', 'estado']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
