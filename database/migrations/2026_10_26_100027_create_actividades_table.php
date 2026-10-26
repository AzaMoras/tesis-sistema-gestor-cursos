<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('actividades', function (Blueprint $table) {
        $table->id();
        $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
        $table->foreignId('modulo_id')->nullable()->constrained('modulos_curso')->restrictOnDelete();
        $table->foreignId('grupo_id')->nullable()->constrained('grupos')->restrictOnDelete();
        $table->string('titulo', 180);
        $table->string('tipo', 25);
        $table->decimal('puntaje_maximo', 8, 2)->default(100);
        $table->decimal('ponderacion', 5, 2)->default(0);
        $table->unsignedSmallInteger('intentos_maximos')->default(1);
        $table->unsignedSmallInteger('plazo_dias')->nullable();
        $table->json('configuracion_json')->nullable();
        $table->string('estado', 25)->default('borrador');
        $table->timestamps();
        $table->softDeletes();
        $table->index(['curso_id', 'tipo']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};
