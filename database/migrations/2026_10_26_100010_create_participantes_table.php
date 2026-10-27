<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('participantes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
        $table->string('telefono', 25)->nullable();
        $table->date('fecha_alta');
        $table->timestamps();
        $table->softDeletes();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('participantes');
    }
};
