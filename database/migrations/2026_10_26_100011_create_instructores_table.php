<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('instructores', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
        $table->string('especialidad', 180);
        $table->text('biografia')->nullable();
        $table->string('estado_validacion', 25)->default('pendiente');
        $table->foreignId('validado_por')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
        $table->softDeletes();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructores');
    }
};
