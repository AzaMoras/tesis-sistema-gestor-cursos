<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('clave', 60)->unique();
        $table->string('nombre', 100);
        $table->string('descripcion', 255)->nullable();
        $table->timestamps();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
