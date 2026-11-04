<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reportes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->foreignId('activo_id')->constrained('activos')->restrictOnDelete();
            $table->foreignId('creador_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('tecnico_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('prioridad', 20)->default('Media'); // Baja, Media, Alta, Critica
            $table->string('estado', 30)->default('Recibido'); // Estructura histórica transformada por una migración posterior
            $table->string('resultado_diagnostico', 30)->nullable(); // no_solucionado (Rojo), solucionado (Verde)
            $table->text('descripcion');
            $table->text('diagnostico')->nullable();
            $table->text('solucion')->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();

            $table->index(['estado', 'prioridad'], 'idx_reportes_estado_prioridad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reportes');
    }
};
