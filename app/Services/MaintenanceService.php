<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Audit;
use App\Models\CalendarTask;
use App\Models\Maintenance;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    public function registerMaintenance(int $assetId, User $technician, string $date, string $detail, array $options = []): Maintenance
    {
        return DB::transaction(function () use ($assetId, $technician, $date, $detail, $options) {
            $taskId = (int) ($options['task_id'] ?? 0);
            $reportId = (int) ($options['report_id'] ?? 0);
            $finding = $options['finding'] ?? null;
            $priority = $options['priority'] ?? 'Media';
            $submission = OperationSubmission::attributes($technician, $options['submission_id'] ?? null,
                [$assetId, $date, $detail, $taskId, $reportId, $finding, $priority]);
            if ($previous = OperationSubmission::previous(Maintenance::class, $submission)) return $previous;

            $asset = Asset::lockForUpdate()->findOrFail($assetId);
            abort_if($asset->retirado, 409, 'El dispositivo está en el histórico.');
            if ($date > now(Report::TIME_ZONE)->toDateString() || ($asset->fecha_alta && $date < $asset->fecha_alta->toDateString())) {
                throw ValidationException::withMessages(['maintenance_date' => 'La ejecución debe estar entre el alta del dispositivo y el día de hoy.']);
            }
            $task = $taskId ? CalendarTask::lockForUpdate()->findOrFail($taskId) : null;
            if ($task) {
                $task->authorizeExecution($technician);
                abort_if(isset($options['task_version']) && (int) $options['task_version'] !== (int) $task->revision, 409, 'La programación cambió. Revise la tarea antes de registrar la inspección.');
                abort_if($task->completed, 409, 'La tarea ya tiene una ejecución. Actualice el calendario.');
                abort_unless($task->tipo === 'mantenimiento' && (int) $task->asset_id === $assetId, 422, 'La tarea y el dispositivo no corresponden a la misma inspección.');
                $task->complete($technician);
            }
            if ($reportId) {
                $report = Report::visibleTo($technician)->whereHas('activos', fn ($query) => $query->whereKey($assetId))->find($reportId);
                abort_unless($report, 422, 'Elija un reporte disponible para este dispositivo.');
            }
            if ($finding) {
                $reportId = app(ReportService::class)->createReport($assetId, $technician, $priority, $finding)->id;
            }

            $nextDate = Maintenance::projectNextDate(Carbon::parse($date), $asset->frecuencia_service)?->toDateString();
            $maintenance = Maintenance::create([
                ...$submission, 'activo_id' => $assetId, 'task_id' => $task?->id, 'reporte_id' => $reportId ?: null,
                'fecha_mantenimiento' => $date, 'descripcion' => $detail, 'technician_id' => $technician->id,
                'technician_name' => $technician->nombre, 'interval_days' => $asset->frecuencia_service,
                'projected_date' => $nextDate,
                'observaciones' => "Registrado por {$technician->nombre}. ".($nextDate ? "Próxima revisión calculada: {$nextDate}." : 'Sin mantenimiento periódico.'),
            ]);
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Inspección {$maintenance->id} de {$asset->codigo} registrada por {$technician->nombre}."]);
            return $maintenance;
        }, 3);
    }
}
