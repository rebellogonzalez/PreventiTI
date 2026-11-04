<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_runs', function (Blueprint $table) {
            $table->id();
            $table->date('reference_date');
            $table->text('manifest');
            $table->timestamp('created_at');
            $table->timestamp('retired_at')->nullable();
        });
        Schema::create('maintenance_budgets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->char('currency', 3)->default('UYU');
            $table->unsignedBigInteger('amount_minor');
            $table->text('scope');
        });
        Schema::create('maintenance_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('activos')->restrictOnDelete();
            $table->foreignId('maintenance_id')->constrained('mantenimientos')->restrictOnDelete();
            $table->date('incurred_on')->index();
            $table->string('kind'); // part, supply, service
            $table->string('name');
            $table->string('category');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_cost_minor'); // Centésimos, nunca moneda implícita.
            $table->char('currency', 3)->default('UYU');
            $table->text('reason');
        });
        Schema::table('alertas', function (Blueprint $table) {
            $table->foreignId('reporte_id')->nullable()->constrained('reportes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alertas', fn (Blueprint $table) => $table->dropConstrainedForeignId('reporte_id'));
        Schema::dropIfExists('maintenance_costs');
        Schema::dropIfExists('maintenance_budgets');
        Schema::dropIfExists('simulation_runs');
    }
};
