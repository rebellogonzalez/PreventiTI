<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auditoria_logs')) {
            return;
        }

        DB::table('auditoria_logs')
            ->where('accion', 'HISTORIAL_DERIVACION')
            ->update([
                'accion' => 'HISTORIAL_TECNICO',
                'detalle' => 'Registro técnico histórico asociado al activo informado.',
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('auditoria_logs')) {
            return;
        }

        DB::table('auditoria_logs')
            ->where('accion', 'HISTORIAL_TECNICO')
            ->update(['accion' => 'HISTORIAL_DERIVACION']);
    }
};
