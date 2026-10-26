<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('sedes', function (Blueprint $table) {
        $table->id();
        $table->string('nombre', 150);
        $table->string('proveedor', 150)->nullable();
        $table->string('region', 150)->nullable();
        $table->string('tipo', 40)->default('infraestructura');
        $table->string('estado', 25)->default('activa');
        $table->timestamps();
        $table->softDeletes();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('sedes');
    }
};
