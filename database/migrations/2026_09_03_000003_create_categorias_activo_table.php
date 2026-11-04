<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_activo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->string('codigo', 50)->unique();
            $table->unsignedInteger('periodicidad_dias');
            $table->boolean('requiere_consumible')->default(false);
            $table->string('tipo_consumible', 100)->nullable();
            $table->string('icon', 50)->default('box');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_activo');
    }
};
