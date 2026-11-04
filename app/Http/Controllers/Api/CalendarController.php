<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCalendarTaskRequest;
use App\Models\Asset;
use App\Models\Audit;
use App\Models\CalendarTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $selected = $request->get('date', now()->toDateString());
        $date = Carbon::parse($selected);
        $query = CalendarTask::query();
        if (! $request->user()->hasHierarchyAtLeast(2)) $query->where('technician_id', $request->user()->id);
        $tasks = (clone $query)->orderBy('fecha')->orderBy('hora')->get();
        return response()->json(['success' => true, 'data' => ['tasks' => $tasks->map(fn (CalendarTask $task) => ['id' => $task->id, 'technician_id' => $task->technician_id, 'date' => $task->fecha->toDateString(), 'time' => $task->hora, 'title' => $task->titulo, 'type' => $task->tipo, 'category' => $task->category, 'technician' => $task->technician_name, 'asset' => $task->asset_code ?: 'Sin asignar', 'priority' => $task->priority, 'completed' => $task->completed]), 'metrics' => ['today' => (clone $query)->where('fecha', $selected)->count(), 'week' => (clone $query)->whereBetween('fecha', [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()])->count(), 'month' => (clone $query)->whereBetween('fecha', [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()])->count()]]]);
    }

    public function store(StoreCalendarTaskRequest $request): JsonResponse
    {
        abort_unless($request->user()->hasHierarchyAtLeast(2), 403);
        $tech = User::findOrFail($request->technician_id);
        abort_unless($tech->activo && \App\Models\Technician::whereKey($tech->id)->exists(), 422, 'Seleccione un técnico activo.');
        $asset = $request->filled('asset_id') ? Asset::find($request->asset_id) : null;
        $task = CalendarTask::create(['fecha' => $request->fecha, 'hora' => $request->hora, 'titulo' => $request->titulo, 'tipo' => $request->tipo, 'category' => match ($request->tipo) {'reporte' => 'Reporte', 'consumible' => 'Consumible', 'red' => 'Red & Seguridad', 'auditoria' => 'Auditoría', default => 'Mantenimiento'}, 'technician_id' => $tech->id, 'technician_name' => $tech->nombre, 'asset_id' => $asset?->id, 'asset_code' => $asset?->codigo, 'priority' => $request->input('priority', 'Media')]);
        Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Tarea {$task->id} agendada para {$tech->nombre}."]);
        return response()->json(['success' => true, 'message' => "Tarea '{$task->titulo}' agendada exitosamente.", 'data' => $task], 201);
    }

    public function toggleCompleted(Request $request, int $id): JsonResponse
    {
        $task = CalendarTask::findOrFail($id);
        abort_unless($request->user()->hasHierarchyAtLeast(2) || (int) $task->technician_id === (int) $request->user()->id, 403);
        $task->completed = ! $task->completed;
        $task->save();
        return response()->json(['success' => true, 'message' => 'Tarea marcada como '.($task->completed ? 'Completada' : 'Pendiente'), 'data' => $task]);
    }
}
