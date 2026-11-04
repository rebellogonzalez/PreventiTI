<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Audit;
use App\Models\Report;
use App\Models\TechnicalChief;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(private readonly AlertNotificationService $alertNotificationService) {}

    public function createReport(int $assetId, User $creator, string $priority, string $description): Report
    {
        return DB::transaction(function () use ($assetId, $creator, $priority, $description) {
            $asset = Asset::findOrFail($assetId);
            if (! $asset->activo) {
                throw new \RuntimeException('No se puede crear un reporte sobre un activo retirado.');
            }
            $urgency = match ($priority) {
                'Critica', 'Crítica' => 'Critico',
                'Alta' => 'Alto',
                'Baja' => 'Bajo',
                default => 'Medio',
            };
            $audit = Audit::create([
                'fecha_auditoria' => now(),
                'descripcion' => "Creación de reporte para {$asset->codigo} por {$creator->nombre}.",
            ]);
            $report = Report::create([
                // jefe_id is the notification recipient, not an automatic
                // technician assignment. The chief assigns explicitly later.
                'jefe_id' => TechnicalChief::query()->value('id'),
                'auditoria_id' => $audit->id,
                'historial' => 'Reporte creado desde la aplicación.',
                'estado_reporte' => 'Recibido/Derivado',
                'urgencia' => $urgency,
                'descripcion' => $description,
                'fecha_creacion' => now(),
            ]);
            $report->activos()->attach($asset->id);
            $report->creadores()->attach($creator->id);
            $report->load(['activos.categoria', 'creadores.nombres']);
            $this->alertNotificationService->notifyNewReport($report);

            return $report;
        });
    }

    public function assignTechnician(Report $report, User $chief, int $technicianId, ?string $reassignmentReason): Report
    {
        return DB::transaction(function () use ($report, $chief, $technicianId, $reassignmentReason) {
            if ($report->estado_reporte === 'Finalizado') {
                throw ValidationException::withMessages(['technician_id' => 'No se puede asignar un reporte finalizado.']);
            }

            $technician = Technician::findOrFail($technicianId);
            if (! User::whereKey($technicianId)->where('activo', true)->exists()) {
                throw ValidationException::withMessages(['technician_id' => 'Seleccione un técnico con una cuenta activa.']);
            }
            if (TechnicalChief::query()->whereKey($technician->id)->exists()) {
                throw ValidationException::withMessages(['technician_id' => 'El Jefe Técnico no puede ser el técnico asignado del reporte.']);
            }
            $activeAssignment = DB::table('tecnico_jefe_tecnico')
                ->where('reporte_id', $report->id)
                ->whereNull('fecha_finalizacion')
                ->lockForUpdate()
                ->first();

            if ($activeAssignment && (int) $activeAssignment->tecnico_id === $technician->id) {
                throw ValidationException::withMessages(['technician_id' => 'El técnico seleccionado ya es el responsable activo.']);
            }

            if ($activeAssignment) {
                if (! $reassignmentReason) {
                    throw ValidationException::withMessages(['reassignment_reason' => 'Debe indicar el motivo de la reasignación.']);
                }

                DB::table('tecnico_jefe_tecnico')
                    ->where('reporte_id', $report->id)
                    ->whereNull('fecha_finalizacion')
                    ->update([
                        'fecha_finalizacion' => now(),
                        'motivo_reasignacion' => $reassignmentReason,
                    ]);
            }

            DB::table('tecnico_jefe_tecnico')->insert([
                'tecnico_id' => $technician->id,
                'jefe_id' => $chief->id,
                'reporte_id' => $report->id,
                'fecha_asignacion' => now(),
                'fecha_finalizacion' => null,
                'motivo_reasignacion' => null,
            ]);

            $name = User::find($technician->id)?->nombre ?? "Técnico {$technician->id}";
            Audit::create([
                'fecha_auditoria' => now(),
                'descripcion' => $activeAssignment
                    ? "Reporte {$report->codigo} reasignado por {$chief->nombre} a {$name}. Motivo: {$reassignmentReason}"
                    : "Reporte {$report->codigo} asignado por {$chief->nombre} a {$name}.",
            ]);

            return $report->fresh();
        });
    }

    public function transition(Report $report, User $chief, string $nextStatus, ?string $actionReport, ?string $finalDiagnosis): Report
    {
        return DB::transaction(function () use ($report, $chief, $nextStatus, $actionReport, $finalDiagnosis) {
            $allowedTransitions = [
                'Recibido/Derivado' => ['En curso'],
                'En curso' => ['Diagnóstico', 'Finalizado'],
                'Diagnóstico' => ['Finalizado'],
            ];

            if (! in_array($nextStatus, $allowedTransitions[$report->estado_reporte] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "La transición de {$report->estado_reporte} a {$nextStatus} no está permitida."]);
            }

            $hasActiveTechnician = DB::table('tecnico_jefe_tecnico')
                ->where('reporte_id', $report->id)
                ->whereNull('fecha_finalizacion')
                ->lockForUpdate()
                ->exists();

            if ($nextStatus === 'En curso' && ! $hasActiveTechnician) {
                throw ValidationException::withMessages(['status' => 'Debe asignar un técnico antes de iniciar el reporte.']);
            }

            if (in_array($nextStatus, ['Diagnóstico', 'Finalizado'], true) && ! $actionReport) {
                throw ValidationException::withMessages(['action_report' => 'Debe registrar el informe de accionar realizado.']);
            }

            if ($nextStatus === 'Diagnóstico' && ! $finalDiagnosis) {
                throw ValidationException::withMessages(['final_diagnosis' => 'Debe registrar el diagnóstico final del dispositivo.']);
            }

            $report->estado_reporte = $nextStatus;
            if ($actionReport) {
                $report->informe_accionar = $actionReport;
                $report->resolucion = $actionReport;
            }
            if ($finalDiagnosis) {
                $report->diagnostico_final = $finalDiagnosis;
            }
            if ($nextStatus === 'Finalizado') {
                $report->fecha_cierre = now();
            }
            $report->save();

            Audit::create([
                'fecha_auditoria' => now(),
                'descripcion' => "Reporte {$report->codigo} cambiado a {$nextStatus} por {$chief->nombre}.",
            ]);

            return $report->fresh();
        });
    }
}
