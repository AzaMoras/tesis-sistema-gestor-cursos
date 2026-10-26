<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('curso_requisito', function (Blueprint $table) {
        $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
        $table->foreignId('requisito_id')->constrained('requisitos')->restrictOnDelete();
        $table->boolean('obligatorio')->default(true);
        $table->primary(['curso_id', 'requisito_id']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_requisito');
    }
};
