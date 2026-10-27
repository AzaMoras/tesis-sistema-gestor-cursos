<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    // Laravel ya crea 'users' en su migración base. Solo agregamos lo que falta.
    Schema::table('users', function (Blueprint $table) {
        $table->string('estado_cuenta', 25)->default('pendiente')->index();
        $table->timestamp('ultimo_acceso_at')->nullable();
        $table->softDeletes();
    });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['ultimo_acceso_at', 'estado_cuenta']);
        });
    }
};
