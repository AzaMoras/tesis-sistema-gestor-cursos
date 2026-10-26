<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('instituciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('sede_id')->nullable()->constrained('sedes')->nullOnDelete();
        $table->string('nombre', 180);
        $table->string('slug', 150)->unique();
        $table->string('correo_contacto', 190)->nullable();
        $table->string('estado', 25)->default('activa');
        $table->timestamps();
        $table->softDeletes();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('instituciones');
    }
};
