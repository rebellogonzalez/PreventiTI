<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->json('historial_operativo')->nullable()->after('descripcion');
        });

        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('reportes')->cascadeOnDelete();
            $table->foreignId('destinatario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('titulo', 180);
            $table->text('mensaje');
            $table->timestamp('leida_at')->nullable();
            $table->timestamps();

            $table->unique(['reporte_id', 'destinatario_id'], 'uq_notificacion_reporte_destinatario');
            $table->index(['destinatario_id', 'leida_at'], 'idx_notificaciones_destinatario_lectura');
        });

        $recipientIds = DB::table('usuarios')
            ->join('roles', 'roles.id', '=', 'usuarios.rol_id')
            ->where('usuarios.activo', true)
            ->where(function ($query) {
                $query->where('usuarios.is_leader', true)
                    ->orWhereIn('roles.nombre', ['administrador', 'tecnico_lider']);
            })
            ->pluck('usuarios.id');

        DB::table('reportes')->orderBy('id')->each(function ($report) use ($recipientIds) {
            $history = [
                'codigo_previo' => $report->codigo,
                'estado_previo' => $report->estado,
                'tecnico_id_previo' => $report->tecnico_id,
                'resultado_previo' => $report->resultado_diagnostico,
                'diagnostico_previo' => $report->diagnostico,
                'solucion_previa' => $report->solucion,
                'fecha_cierre_previa' => $report->fecha_cierre,
            ];
            $year = $report->created_at ? substr((string) $report->created_at, 0, 4) : date('Y');
            $newCode = 'REP-'.$year.'-'.str_pad((string) $report->id, 4, '0', STR_PAD_LEFT);

            DB::table('reportes')->where('id', $report->id)->update([
                'codigo' => $newCode,
                'historial_operativo' => json_encode($history, JSON_UNESCAPED_UNICODE),
            ]);

            $asset = DB::table('activos')->where('id', $report->activo_id)->first();
            foreach ($recipientIds as $recipientId) {
                DB::table('notificaciones')->insert([
                    'reporte_id' => $report->id,
                    'destinatario_id' => $recipientId,
                    'titulo' => "Nuevo reporte {$newCode}",
                    'mensaje' => 'Se informó una situación en '.($asset->codigo ?? 'un activo').': '.($asset->nombre ?? 'sin nombre').'.',
                    'leida_at' => null,
                    'created_at' => $report->created_at,
                    'updated_at' => $report->updated_at,
                ]);
            }
        });

        if (Schema::hasTable('auditoria_logs')) {
            DB::table('auditoria_logs')->orderBy('id')->each(function ($log) {
                DB::table('auditoria_logs')->where('id', $log->id)->update([
                    'accion' => str_ireplace(
                        ['CREACION_TICKET', 'DERIVACION_TICKET', 'CIERRE_TICKET'],
                        ['CREACION_REPORTE', 'HISTORIAL_DERIVACION', 'HISTORIAL_CIERRE'],
                        $log->accion,
                    ),
                    'entidad' => str_ireplace(['Ticket', 'TCK-'], ['Reporte', 'REP-'], $log->entidad),
                    'detalle' => str_ireplace(['tickets', 'ticket', 'TCK-'], ['reportes', 'reporte', 'REP-'], $log->detalle),
                ]);
            });
        }

        Schema::table('reportes', function (Blueprint $table) {
            $table->dropIndex('idx_reportes_estado_prioridad');
            $table->dropForeign(['tecnico_id']);
            $table->dropColumn([
                'tecnico_id',
                'estado',
                'resultado_diagnostico',
                'diagnostico',
                'solucion',
                'fecha_cierre',
            ]);
            $table->index('prioridad', 'idx_reportes_prioridad');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');

        Schema::table('reportes', function (Blueprint $table) {
            $table->dropIndex('idx_reportes_prioridad');
            $table->foreignId('tecnico_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('estado', 30)->default('Recibido');
            $table->string('resultado_diagnostico', 30)->nullable();
            $table->text('diagnostico')->nullable();
            $table->text('solucion')->nullable();
            $table->timestamp('fecha_cierre')->nullable();
        });

        DB::table('reportes')->orderBy('id')->each(function ($report) {
            $history = json_decode($report->historial_operativo ?: '{}', true);
            DB::table('reportes')->where('id', $report->id)->update([
                'codigo' => $history['codigo_previo'] ?? $report->codigo,
                'estado' => $history['estado_previo'] ?? 'Recibido',
                'tecnico_id' => $history['tecnico_id_previo'] ?? null,
                'resultado_diagnostico' => $history['resultado_previo'] ?? null,
                'diagnostico' => $history['diagnostico_previo'] ?? null,
                'solucion' => $history['solucion_previa'] ?? null,
                'fecha_cierre' => $history['fecha_cierre_previa'] ?? null,
            ]);
        });

        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn('historial_operativo');
            $table->index(['estado', 'prioridad'], 'idx_reportes_estado_prioridad');
        });
    }
};
