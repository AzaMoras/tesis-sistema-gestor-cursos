<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('grupos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('edicion_id')->constrained('ediciones')->restrictOnDelete();
        $table->string('codigo', 60);
        $table->unsignedInteger('cupo_maximo')->nullable();
        $table->string('estado', 25)->default('borrador');
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['edicion_id', 'codigo']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
