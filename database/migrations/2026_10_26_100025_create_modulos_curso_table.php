<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('modulos_curso', function (Blueprint $table) {
        $table->id();
        $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
        $table->string('titulo', 180);
        $table->text('descripcion')->nullable();
        $table->unsignedSmallInteger('orden');
        $table->boolean('obligatorio')->default(true);
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['curso_id', 'orden']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('modulos_curso');
    }
};
