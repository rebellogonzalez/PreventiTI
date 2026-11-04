<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumibles_activo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->string('tipo', 100);
            $table->decimal('nivel_actual', 5, 2)->default(100.00);
            $table->decimal('nivel_minimo', 5, 2)->default(20.00);
            $table->timestamps();

            $table->index(['activo_id', 'nivel_actual'], 'idx_consumibles_nivel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumibles_activo');
    }
};
