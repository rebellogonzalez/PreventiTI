<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas_calendario', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('hora', 10)->default('09:00');
            $table->string('titulo', 200);
            $table->string('tipo', 50)->default('mantenimiento');
            $table->string('category', 100)->default('Mantenimiento');
            $table->foreignId('technician_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('technician_name', 150)->nullable();
            $table->foreignId('asset_id')->nullable()->constrained('activos')->nullOnDelete();
            $table->string('asset_code', 50)->nullable();
            $table->string('priority', 20)->default('Media');
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->index(['fecha', 'completed'], 'idx_calendario_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas_calendario');
    }
};
