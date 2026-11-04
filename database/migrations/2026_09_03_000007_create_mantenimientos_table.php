<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->restrictOnDelete();
            $table->foreignId('tecnico_id')->constrained('usuarios')->restrictOnDelete();
            $table->date('fecha_mantenimiento');
            $table->date('proxima_fecha');
            $table->unsignedInteger('periodicity_days');
            $table->text('detalle');
            $table->timestamps();

            $table->index(['activo_id', 'proxima_fecha'], 'idx_mantenimientos_proxima');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mantenimientos');
    }
};
