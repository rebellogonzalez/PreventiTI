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

        DB::table('auditoria_logs')->orderBy('id')->each(function ($log) {
            $action = $log->accion;
            $detail = $log->detalle;

            if (in_array($action, ['CREACIÓN_TICKET', 'CREACION_TICKET'], true)) {
                $action = 'CREACION_REPORTE';
            } elseif (in_array($action, ['ASIGNACIÓN_TICKET', 'DERIVACION_TICKET', 'HISTORIAL_DERIVACION'], true)) {
                $action = 'HISTORIAL_TECNICO';
                $detail = 'Registro técnico histórico asociado al activo informado.';
            } elseif ($action === 'DIAGNOSTICO_NO_SOLUCIONADO') {
                $action = 'HISTORIAL_TECNICO';
                $lastMarker = strrpos($detail, 'Diagnóstico: ');
                $historicalDetail = $lastMarker === false ? $detail : substr($detail, $lastMarker + strlen('Diagnóstico: '));
                $detail = 'Registro técnico histórico: '.$historicalDetail;
            } elseif ($action === 'HISTORIAL_CIERRE') {
                $action = 'HISTORIAL_TECNICO';
                $detail = 'Registro técnico histórico de resolución asociado al reporte.';
            }

            DB::table('auditoria_logs')->where('id', $log->id)->update([
                'accion' => $action,
                'detalle' => $detail,
            ]);
        });
    }

    public function down(): void
    {
        // La normalización mantiene la información de autor, entidad y fecha; no restaura etiquetas obsoletas.
    }
};
