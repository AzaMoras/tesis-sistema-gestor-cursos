<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('usuario_rol', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
        $table->foreignId('rol_id')->constrained('roles')->restrictOnDelete();
        $table->foreignId('institucion_id')->constrained('instituciones')->restrictOnDelete();
        $table->foreignId('asignado_por')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
        $table->unique(['user_id', 'rol_id', 'institucion_id'], 'urol_usr_rol_inst_uq');
        $table->index(['institucion_id', 'rol_id']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_rol');
    }
};
