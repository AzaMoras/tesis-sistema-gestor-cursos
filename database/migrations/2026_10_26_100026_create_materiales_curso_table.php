<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('materiales_curso', function (Blueprint $table) {
        $table->id();
        $table->foreignId('modulo_id')->constrained('modulos_curso')->restrictOnDelete();
        $table->string('titulo', 180);
        $table->string('tipo', 25);
        $table->string('ruta_archivo', 500)->nullable();
        $table->string('url_externa', 1000)->nullable();
        $table->unsignedSmallInteger('orden')->default(1);
        $table->boolean('publicado')->default(false);
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['modulo_id', 'orden']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('materiales_curso');
    }
};
