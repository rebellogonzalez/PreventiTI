<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias_activo')->restrictOnDelete();
            $table->foreignId('ubicacion_id')->constrained('ubicaciones')->restrictOnDelete();
            $table->string('codigo', 50)->unique();
            $table->string('serie', 100)->unique();
            $table->string('nombre', 200);
            $table->string('responsable', 150);
            $table->string('estado', 50)->default('Operativo');
            $table->unsignedInteger('periodicidad_dias');
            $table->date('last_maintenance')->nullable();
            $table->date('next_maintenance')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['categoria_id', 'ubicacion_id', 'activo'], 'idx_activos_compuesto');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activos');
    }
};
