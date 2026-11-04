<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_map_positions', function (Blueprint $table) {
            $table->foreignId('activo_id')->primary()->constrained('activos')->cascadeOnDelete();
            $table->string('floor');
            $table->string('room');
            $table->double('x'); // normalized within the inventory room, 0..1
            $table->double('z');
            $table->double('elevation')->default(0); // metres above the floor
            $table->double('rotation')->default(0); // degrees
            $table->foreignId('updated_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_map_positions');
    }
};
