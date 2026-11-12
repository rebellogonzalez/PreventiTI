<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaintenanceRequest;
use App\Models\Asset;
use App\Models\Maintenance;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Audit;
use App\Models\Report;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $maintenanceService) {}

    private function user(Request $request): ?User
    {
        return $request->user();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        if ($user->isEmployee()) return response()->json(['success' => false, 'error' => 'No autorizado para consultar mantenimientos preventivos.'], 403);
        $visibleReports = Report::visibleTo($user)->pluck('id')->flip();
        $data = Maintenance::with('activo.categoria')->orderByDesc('fecha_mantenimiento')->get()->map(fn (Maintenance $maintenance) => [
            'id' => $maintenance->id, 'asset_id' => $maintenance->activo_id, 'asset_code' => $maintenance->activo?->codigo,
            'asset_name' => $maintenance->activo?->nombre, 'maintenance_date' => $maintenance->fecha_mantenimiento->toDateString(),
            'next_date' => $maintenance->proxima_fecha?->toDateString(), 'periodicity_days' => $maintenance->periodicity_days,
            'technician_id' => $maintenance->technician_id, 'technician_name' => $maintenance->recordedTechnician(), 'task_id' => $maintenance->task_id,
            'report_id' => $visibleReports->has($maintenance->reporte_id) ? $maintenance->reporte_id : null,
            'requires_review' => $maintenance->requires_review,
            'detail' => $maintenance->descripcion, 'observations' => $maintenance->observaciones,
        ]);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function correctDate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['maintenance_date' => 'required|date_format:Y-m-d|before_or_equal:'.now(Report::TIME_ZONE)->toDateString()]);
        DB::transaction(function () use ($request, $id, $data) {
            $record = Maintenance::lockForUpdate()->findOrFail($id);
            abort_unless($record->requires_review, 409, 'La fecha ya fue confirmada.');
            $asset = Asset::lockForUpdate()->findOrFail($record->activo_id);
            $date = $data['maintenance_date'];
            abort_if($asset->fecha_alta && $date < $asset->fecha_alta->toDateString(), 422, 'La fecha es anterior al alta del dispositivo.');
            $previous = $record->fecha_mantenimiento->toDateString();
            $interval = $record->periodicity_days ?? $asset->frecuencia_service;
            $record->fecha_mantenimiento = $date;
            $record->interval_days = $interval;
            $record->projected_date = Maintenance::projectNextDate($record->fecha_mantenimiento, $interval);
            $projection = $record->projected_date?->toDateString();
            $record->observaciones = trim($record->observaciones." Fecha verificada por {$request->user()->nombre}: {$date}. ".($projection ? "Vencimiento corregido: {$projection}." : 'Sin mantenimiento periódico.'));
            $record->requires_review = false;
            $record->save();
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Fecha de inspección {$id} verificada por {$request->user()->nombre}: {$previous} → {$date}."]);
        }, 3);
        return response()->json(['success' => true, 'message' => 'Fecha de ejecución confirmada.']);
    }

    public function store(StoreMaintenanceRequest $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $asset = Asset::with('categoria')->findOrFail($request->asset_id);
        if ($user->isEmployee() || (! $user->isLeader() && ! $user->isAdmin() && ! $user->canManageAssetSpecialty($asset))) return response()->json(['success' => false, 'error' => 'No autorizado para registrar mantenimiento en este activo.'], 403);
        $maintenance = $this->maintenanceService->registerMaintenance($asset->id, $user, $request->maintenance_date, $request->detail, $request->validated());
        $nextDate = $maintenance->proxima_fecha?->toDateString();
        return response()->json(['success' => true, 'message' => 'Mantenimiento registrado. '.($nextDate ? "Próxima fecha calculada: {$nextDate}." : 'Sin mantenimiento periódico.'), 'data' => $maintenance], 201);
    }
}
