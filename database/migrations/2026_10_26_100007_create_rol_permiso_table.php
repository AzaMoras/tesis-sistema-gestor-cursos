<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('rol_permiso', function (Blueprint $table) {
        $table->foreignId('rol_id')->constrained('roles')->cascadeOnDelete();
        $table->foreignId('permiso_id')->constrained('permisos')->cascadeOnDelete();
        $table->primary(['rol_id', 'permiso_id']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_permiso');
    }
};
