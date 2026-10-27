<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('ediciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
        $table->string('clave', 60);
        $table->string('modalidad', 25)->default('acompanada');
        $table->date('fecha_inicio');
        $table->date('fecha_fin');
        $table->timestamp('cierre_inscripciones_at')->nullable();
        $table->decimal('precio', 12, 2)->default(0);
        $table->char('moneda', 3)->default('MXN');
        $table->decimal('asistencia_minima', 5, 2)->default(0);
        $table->decimal('calificacion_minima', 5, 2)->default(70);
        $table->string('estado', 25)->default('borrador');
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['curso_id', 'clave']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('ediciones');
    }
};
