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
use Illuminate\Support\Facades\Auth;

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
        $data = Maintenance::with('activo.categoria')->orderByDesc('fecha_mantenimiento')->get()->map(fn (Maintenance $maintenance) => [
            'id' => $maintenance->id, 'asset_id' => $maintenance->activo_id, 'asset_code' => $maintenance->activo?->codigo,
            'asset_name' => $maintenance->activo?->nombre, 'maintenance_date' => $maintenance->fecha_mantenimiento->toDateString(),
            'next_date' => $maintenance->proxima_fecha?->toDateString(), 'periodicity_days' => $maintenance->periodicity_days,
            'detail' => $maintenance->descripcion, 'observations' => $maintenance->observaciones,
        ]);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(StoreMaintenanceRequest $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $asset = Asset::with('categoria')->findOrFail($request->asset_id);
        if ($user->isEmployee() || (! $user->isLeader() && ! $user->isAdmin() && ! $user->canManageAssetSpecialty($asset))) return response()->json(['success' => false, 'error' => 'No autorizado para registrar mantenimiento en este activo.'], 403);
        $maintenance = $this->maintenanceService->registerMaintenance($asset->id, $user, $request->maintenance_date, $request->detail);
        return response()->json(['success' => true, 'message' => "Mantenimiento registrado. Próxima fecha calculada a {$maintenance->proxima_fecha?->toDateString()}.", 'data' => $maintenance], 201);
    }
}
