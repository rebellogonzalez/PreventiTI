<?php

use App\Support\MariaDbRules;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (MariaDbRules::enabled()) MariaDbRules::install();
    }

    public function down(): void
    {
        if (MariaDbRules::enabled()) {
            throw new RuntimeException('La reversión de MariaDB requiere restaurar un respaldo; no se elimina el historial automáticamente.');
        }
    }
};
