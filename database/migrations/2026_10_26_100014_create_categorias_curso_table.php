<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('categorias_curso', function (Blueprint $table) {
        $table->id();
        $table->foreignId('institucion_id')->constrained('instituciones')->restrictOnDelete();
        $table->string('nombre', 120);
        $table->string('slug', 150);
        $table->text('descripcion')->nullable();
        $table->string('estado', 25)->default('activa');
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['institucion_id', 'slug'], 'cat_inst_slug_uq');
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_curso');
    }
};
