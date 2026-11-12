<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCalendarTaskRequest;
use App\Models\Asset;
use App\Models\Audit;
use App\Models\CalendarTask;
use App\Models\Report;
use App\Models\Technician;
use App\Models\User;
use App\Services\OperationSubmission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['date' => 'sometimes|date_format:Y-m-d']);
        $selected = $request->get('date', now(Report::TIME_ZONE)->toDateString());
        $date = Carbon::parse($selected);
        $query = CalendarTask::query();
        if (! $request->user()->hasHierarchyAtLeast(2)) $query->where('technician_id', $request->user()->id);
        $reports = Report::visibleTo($request->user())->pluck('id')->flip();
        $tasks = (clone $query)->with('inspection:id,task_id')->get()->sortBy(fn ($task) => $task->scheduleSortKey())->values();
        $query->whereNull('cancel_reason');
        return response()->json(['success' => true, 'data' => [
            'tasks' => $tasks->map(fn ($task) => [
                'id' => $task->id, 'technician_id' => $task->technician_id, 'date' => $task->fecha->toDateString(),
                'time' => $task->hora, 'title' => $task->titulo, 'type' => $task->tipo, 'category' => $task->category,
                'technician' => $task->technician_name, 'asset' => $task->asset_code ?: 'Sin asignar', 'asset_id' => $task->asset_id,
                'priority' => $task->priority, 'completed' => $task->completed, 'cancel_reason' => $task->cancel_reason,
                'inspection_id' => $task->inspection?->id, 'version' => (int) $task->revision,
                'report_id' => $reports->has($task->reporte_id) ? $task->reporte_id : null,
            ]),
            'metrics' => ['today' => (clone $query)->where('fecha', $selected)->count(),
                'week' => (clone $query)->whereBetween('fecha', [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()])->count(),
                'month' => (clone $query)->whereBetween('fecha', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])->count()],
        ]]);
    }

    public function store(StoreCalendarTaskRequest $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->hasHierarchyAtLeast(2), 403);
        $task = DB::transaction(function () use ($request, $actor) {
            $assetId = $request->integer('asset_id');
            $techId = $request->integer('technician_id');
            $reportId = $request->integer('report_id');
            $priority = $request->input('priority', 'Media');
            $submission = OperationSubmission::attributes($actor, $request->submission_id,
                [$request->fecha, $request->hora, $request->titulo, $request->tipo, $techId, $assetId, $reportId, $priority]);
            if ($previous = OperationSubmission::previous(CalendarTask::class, $submission)) return $previous;
            $tech = $this->activeTechnician($techId);
            $asset = $assetId ? Asset::lockForUpdate()->findOrFail($assetId) : null;
            abort_if($asset?->retirado, 409, 'El dispositivo está retirado.');
            abort_if($request->tipo === 'consumible' && $asset && ! $asset->consumible()->exists(), 422, 'El dispositivo no tiene un insumo configurado.');
            if ($reportId) {
                $report = Report::visibleTo($actor)->whereHas('activos', fn ($q) => $q->whereKey($assetId))->lockForUpdate()->find($reportId);
                abort_unless($asset && $report, 422, 'Seleccione el reporte y su dispositivo.');
                abort_unless(Gate::forUser($tech)->allows('view', $report), 422, 'El responsable debe tener acceso al reporte. Revise su asignación en Reportes.');
            }
            $task = CalendarTask::create([
                ...$submission, 'fecha' => $request->fecha, 'hora' => $request->hora, 'titulo' => $request->titulo, 'tipo' => $request->tipo,
                'category' => match ($request->tipo) {
                    'reporte' => 'Reporte', 'consumible' => 'Consumible', 'red' => 'Red & Seguridad',
                    'auditoria' => 'Auditoría', default => 'Mantenimiento',
                },
                'technician_id' => $tech->id, 'technician_name' => $tech->nombre, 'reporte_id' => $reportId ?: null,
                'asset_id' => $asset?->id, 'asset_code' => $asset?->codigo, 'priority' => $priority,
            ]);
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Tarea {$task->id} agendada para {$tech->nombre} por {$actor->nombre}."]);
            return $task;
        }, 3);
        return response()->json(['success' => true, 'message' => "Tarea '{$task->titulo}' agendada.", 'data' => $task], 201);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $request->validate(['technician_id' => 'required|integer']);
        return $this->changePlan($request, $id, true);
    }

    public function edit(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'expected_version' => 'required|integer|min:0', 'technician_id' => 'required_without:cancel_reason|integer',
            'fecha' => 'required_without:cancel_reason|date_format:Y-m-d', 'hora' => 'required_without:cancel_reason|date_format:H:i',
            'cancel_reason' => 'sometimes|required|string|max:255',
        ]);
        return $this->changePlan($request, $id, false);
    }

    private function changePlan(Request $request, int $id, bool $vacantOnly): JsonResponse
    {
        $task = DB::transaction(function () use ($request, $id, $vacantOnly) {
            $tech = $request->filled('technician_id') ? $this->activeTechnician($request->integer('technician_id')) : null;
            $task = CalendarTask::lockForUpdate()->findOrFail($id);
            abort_if($task->completed || $task->cancel_reason || ($vacantOnly && $task->technician_id !== null)
                || (! $vacantOnly && $request->integer('expected_version') !== (int) $task->revision), 409, 'La tarea cambió. Actualice la agenda.');
            $before = "{$task->fecha->toDateString()} {$task->hora}, ".($task->technician_name ?: 'sin responsable');
            if ($request->filled('cancel_reason')) {
                $task->cancel_reason = $request->cancel_reason;
                $change = "cancelada: {$task->cancel_reason}";
            } else {
                if ($task->reporte_id) {
                    abort_unless(Gate::forUser($tech)->allows('view', Report::findOrFail($task->reporte_id)), 422, 'El técnico elegido necesita acceso al reporte.');
                }
                $task->technician_id = $tech->id;
                $task->technician_name = $tech->nombre;
                $task->fecha = $request->input('fecha', $task->fecha);
                $task->hora = $request->input('hora', $task->hora);
                $change = "{$task->fecha->toDateString()} {$task->hora}, {$tech->nombre}";
            }
            $task->revision++;
            $task->save();
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Tarea {$id}: {$before} → {$change}. Registrado por {$request->user()->nombre}."]);
            return $task;
        }, 3);
        return response()->json(['success' => true, 'message' => 'Agenda actualizada.', 'data' => $task]);
    }

    private function activeTechnician(int $id): User
    {
        $user = User::whereKey($id)->sharedLock()->first();
        if (! $user?->activo || ! Technician::whereKey($id)->exists()) {
            throw ValidationException::withMessages(['technician_id' => 'Seleccione un técnico activo.']);
        }
        return $user;
    }

    public function toggleCompleted(Request $request, int $id): JsonResponse
    {
        $request->validate(['completed' => 'sometimes|required|boolean', 'expected_version' => 'sometimes|integer|min:0']);
        $desired = $request->boolean('completed', true);
        $task = DB::transaction(function () use ($request, $id, $desired) {
            $task = CalendarTask::lockForUpdate()->findOrFail($id);
            $actor = $request->user();
            $task->authorizeExecution($actor);
            if ($desired === $task->completed) return $task;
            abort_if($request->has('expected_version') && (int) $task->revision !== $request->integer('expected_version'), 409, 'La tarea cambió. Actualice la agenda.');
            abort_if($task->asset_id && in_array($task->tipo, ['mantenimiento', 'consumible'], true), 422,
                'Registre el resultado de la intervención desde la tarea.');
            if ($desired) {
                $task->complete($actor);
            } else {
                if (! User::whereKey($task->technician_id)->sharedLock()->value('activo')) {
                    $task->technician_id = $task->technician_name = null;
                }
                $task->completed = false;
                $task->revision++;
                $task->save();
                Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Tarea {$task->id} reabierta por {$actor->nombre}."]);
            }
            return $task;
        }, 3);
        return response()->json(['success' => true, 'message' => $task->completed ? 'Tarea completada.' : 'Tarea pendiente.', 'data' => $task]);
    }
}
