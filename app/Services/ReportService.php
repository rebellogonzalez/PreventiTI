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

    public function createReport(int $assetId, User $creator, string $priority, string $description, ?string $submissionId = null): Report
    {
        return DB::transaction(function () use ($assetId, $creator, $priority, $description, $submissionId) {
            abort_unless(User::whereKey($creator->id)->lockForUpdate()->value('activo'), 403);
            $key = $submissionId ? hash('sha256', $creator->id.'/'.strtolower($submissionId)) : null;
            if ($key && ($existing = Report::where('clave_envio', $key)->lockForUpdate()->first())) {
                abort_if($existing->activo_id !== $assetId || $existing->prioridad !== $priority || $existing->descripcion !== $description,
                    409, 'El identificador de envío ya corresponde a otro reporte.');
                return $existing;
            }
            // El traslado de un equipo adquiere este mismo bloqueo antes de cambiar su sala.
            $asset = Asset::lockForUpdate()->find($assetId);
            if (! $asset || ! Asset::inReportAreasFor($creator)->whereKey($assetId)->exists()) {
                throw ValidationException::withMessages(['asset_id' => 'El dispositivo no está disponible para crear un reporte con esta cuenta.']);
            }
            if ($asset->retirado) {
                throw ValidationException::withMessages(['asset_id' => 'No se puede crear un reporte sobre un activo retirado.']);
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
            $report = new Report([
                // jefe_id is the notification recipient, not an automatic
                // technician assignment. The chief assigns explicitly later.
                'jefe_id' => TechnicalChief::firstActiveId(),
                'auditoria_id' => $audit->id,
                'historial' => 'Reporte creado desde la aplicación.',
                'estado_reporte' => 'Recibido/Derivado',
                'urgencia' => $urgency,
                'descripcion' => $description,
                'fecha_creacion' => now(),
            ]);
            $report->clave_envio = $key;
            $report->save();
            $report->activos()->attach($asset->id);
            $report->creadores()->attach($creator->id);
            $report->load(['activos.categoria', 'creadores.nombres']);
            $this->alertNotificationService->notifyNewReport($report);

            return $report;
        }, 3);
    }

    public function assignTechnician(Report $report, User $chief, int $technicianId, ?string $reassignmentReason, ?int $expectedVersion = null): Report
    {
        return DB::transaction(function () use ($report, $chief, $technicianId, $reassignmentReason, $expectedVersion) {
            $accounts = User::whereKey([$chief->id, $technicianId])->orderBy('id')->sharedLock()->get()->keyBy('id');
            abort_unless($accounts->get($chief->id)?->activo, 403);
            if (! $accounts->get($technicianId)?->activo) {
                throw ValidationException::withMessages(['technician_id' => 'Seleccione un técnico con una cuenta activa.']);
            }
            $report = Report::lockForUpdate()->findOrFail($report->id);
            abort_if($expectedVersion !== null && $expectedVersion !== $report->revision_flujo, 409,
                'Otro usuario actualizó el reporte. Revisa los datos vigentes antes de guardar.');
            if ($report->isClosed()) {
                throw ValidationException::withMessages(['technician_id' => 'No se puede asignar un reporte finalizado.']);
            }

            $technician = Technician::findOrFail($technicianId);
            if (TechnicalChief::query()->whereKey($technician->id)->exists()) {
                throw ValidationException::withMessages(['technician_id' => 'El Jefe Técnico no puede ser el técnico asignado del reporte.']);
            }
            $activeAssignment = DB::table('tecnico_jefe_tecnico')
                ->where('reporte_id', $report->id)
                ->whereNull('fecha_finalizacion')
                ->lockForUpdate()
                ->first();

            if (DatabaseWorkflow::enabled()) {
                DatabaseWorkflow::context($chief, $reassignmentReason, fn () => DatabaseWorkflow::call(
                    'asignar_tecnico', [$report->id, $technicianId, $chief->id, $reassignmentReason], 'technician_id',
                ));
                $name = $accounts->get($technicianId)->nombre;
                Audit::create(['fecha_auditoria' => now(), 'descripcion' => $activeAssignment
                    ? "Reporte {$report->codigo} reasignado por {$chief->nombre} a {$name}. Motivo: {$reassignmentReason}"
                    : "Reporte {$report->codigo} asignado por {$chief->nombre} a {$name}."]);
                return $report->fresh();
            }

            if ($activeAssignment && (int) $activeAssignment->tecnico_id === $technician->id) {
                // Older reports may already have a responsible technician while still in reception.
                if ($report->estado_reporte === 'Recibido/Derivado') {
                    return $this->saveStatus($report, $chief, 'En curso');
                }
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

            $name = $accounts->get($technicianId)->nombre;
            Audit::create([
                'fecha_auditoria' => now(),
                'descripcion' => $activeAssignment
                    ? "Reporte {$report->codigo} reasignado por {$chief->nombre} a {$name}. Motivo: {$reassignmentReason}"
                    : "Reporte {$report->codigo} asignado por {$chief->nombre} a {$name}.",
            ]);

            if ($report->estado_reporte === 'Recibido/Derivado') {
                return $this->saveStatus($report, $chief, 'En curso');
            }
            $report->increment('revision_flujo');
            return $report->fresh();
        }, 3);
    }

    public function transition(Report $report, User $actor, string $nextStatus, ?string $actionReport, ?string $finalDiagnosis, ?int $expectedVersion = null): Report
    {
        return DB::transaction(function () use ($report, $actor, $nextStatus, $actionReport, $finalDiagnosis, $expectedVersion) {
            $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
            $report = Report::lockForUpdate()->findOrFail($report->id);
            $technicianId = DB::table('tecnico_jefe_tecnico')
                ->where('reporte_id', $report->id)
                ->whereNull('fecha_finalizacion')
                ->lockForUpdate()
                ->value('tecnico_id');
            abort_unless($actor->can('transition', [$report, $technicianId]), 403,
                'No tienes permiso para cambiar el estado actual de este reporte.');
            abort_if($expectedVersion !== null && $expectedVersion !== $report->revision_flujo, 409,
                'Otro usuario actualizó el reporte. Revisa los datos vigentes antes de guardar.');
            if (DatabaseWorkflow::enabled()) {
                return $this->transitionInDatabase($report, $actor, $nextStatus, $actionReport, $finalDiagnosis);
            }
            $allowedTransitions = [
                'Recibido/Derivado' => ['En curso'],
                'En curso' => ['Diagnóstico', 'Finalizado'],
                'Diagnóstico' => ['Finalizado'],
            ];

            if ($report->isClosed() || ! in_array($nextStatus, $allowedTransitions[$report->estado_reporte] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "La transición de {$report->estado_reporte} a {$nextStatus} no está permitida."]);
            }

            if ($technicianId === null) {
                throw ValidationException::withMessages(['status' => 'Asigne un técnico activo antes de avanzar el reporte.']);
            }

            $notes = $report->intervenciones ?? [];
            if ($report->estado_reporte === 'Diagnóstico') {
                // Imported diagnoses may have no recorded author or stage timestamp.
                if (! $notes && $report->informe_accionar) {
                    $notes[] = ['stage' => 'Diagnóstico', 'text' => $report->informe_accionar, 'diagnosis' => $report->diagnostico_final,
                        'author_id' => null, 'author' => null, 'at' => null];
                }
                $actionReport ??= $report->informe_accionar;
            }
            if ($nextStatus === 'En curso') {
                $actionReport = $finalDiagnosis = null;
            }
            if (in_array($nextStatus, ['Diagnóstico', 'Finalizado'], true) && ! $actionReport) {
                throw ValidationException::withMessages(['action_report' => 'Debe registrar el informe de accionar realizado.']);
            }

            if ($nextStatus === 'Diagnóstico' && ! $finalDiagnosis) {
                throw ValidationException::withMessages(['final_diagnosis' => 'Debe registrar el diagnóstico final del dispositivo.']);
            }

            if ($actionReport) {
                $notes[] = ['stage' => $nextStatus, 'text' => $actionReport, 'diagnosis' => $finalDiagnosis,
                    'author_id' => $actor->id, 'author' => $actor->nombre, 'at' => now(Report::TIME_ZONE)->format('Y-m-d H:i:s')];
                $report->intervenciones = $notes;
                $report->informe_accionar = $actionReport;
                $report->resolucion = $actionReport;
            }
            if ($finalDiagnosis) {
                $report->diagnostico_final = $finalDiagnosis;
            }
            if ($nextStatus === 'Finalizado') {
                $report->fecha_cierre = now();
                DB::table('tecnico_jefe_tecnico')->where('reporte_id', $report->id)->whereNull('fecha_finalizacion')
                    ->update(['fecha_finalizacion' => $report->fecha_cierre]);
            }
            return $this->saveStatus($report, $actor, $nextStatus);
        }, 3);
    }

    private function saveStatus(Report $report, User $actor, string $status): Report
    {
        $report->estado_reporte = $status;
        $report->revision_flujo++;
        $report->save();
        Audit::create([
            'fecha_auditoria' => now(),
            'descripcion' => "Reporte {$report->codigo} cambiado a {$status} por {$actor->nombre}.",
        ]);

        return $report->fresh();
    }

    private function transitionInDatabase(Report $report, User $actor, string $status, ?string $action, ?string $diagnosis): Report
    {
        $notes = $report->intervenciones ?? [];
        if ($report->estado_reporte === 'Diagnóstico') {
            if (! $notes && $report->informe_accionar) {
                $notes[] = ['stage' => 'Diagnóstico', 'text' => $report->informe_accionar, 'diagnosis' => $report->diagnostico_final,
                    'author_id' => null, 'author' => null, 'at' => null];
            }
            $action ??= $report->informe_accionar;
        }
        if ($status === 'En curso') $action = $diagnosis = null;
        DatabaseWorkflow::context($actor, null, fn () => DatabaseWorkflow::call(
            'cambiar_estado_reporte', [$report->id, $status, $action, $diagnosis], 'status',
        ));
        $report = $report->fresh();
        if ($action) {
            $notes[] = ['stage' => $status, 'text' => $action, 'diagnosis' => $diagnosis,
                'author_id' => $actor->id, 'author' => $actor->nombre, 'at' => now(Report::TIME_ZONE)->format('Y-m-d H:i:s')];
            $report->intervenciones = $notes;
            $report->save();
        }
        Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Reporte {$report->codigo} cambiado a {$status} por {$actor->nombre}."]);
        return $report->fresh();
    }
}
