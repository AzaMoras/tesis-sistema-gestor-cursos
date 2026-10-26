<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('pagos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('inscripcion_id')->constrained('inscripciones')->restrictOnDelete();
        $table->string('referencia_interna', 120)->unique();
        $table->string('referencia_externa', 180)->nullable()->unique();
        $table->decimal('monto', 12, 2);
        $table->char('moneda', 3)->default('MXN');
        $table->string('metodo', 40);
        $table->string('proveedor', 80)->nullable();
        $table->string('estado', 25)->default('pendiente');
        $table->string('ruta_comprobante', 500)->nullable();
        $table->decimal('monto_reembolsado', 12, 2)->default(0);
        $table->timestamp('fecha_pago')->nullable();
        $table->foreignId('verificado_por')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
        $table->index(['inscripcion_id', 'estado']);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
