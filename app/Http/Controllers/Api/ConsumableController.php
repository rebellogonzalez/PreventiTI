<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateConsumableRequest;
use App\Models\Asset;
use App\Models\Audit;
use App\Models\User;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConsumableController extends Controller
{
    public function __construct(private readonly AlertService $alertService) {}

    private function user(Request $request): ?User
    {
        return $request->user();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        if ($user->isEmployee()) return response()->json(['success' => false, 'error' => 'No autorizado para consultar insumos críticos.'], 403);
        $assets = Asset::has('consumible')->with(['categoria', 'ubicacionDetalle', 'consumable'])->get();
        return response()->json(['success' => true, 'data' => $assets->map(fn (Asset $asset) => ['id' => $asset->id, 'code' => $asset->codigo, 'name' => $asset->nombre, 'category_name' => $asset->categoria?->nombre, 'location_name' => $asset->ubicacionDetalle?->nombre ?? $asset->ubicacion, 'consumable_type' => $asset->consumable?->tipo, 'consumable_level' => $asset->consumable?->nivel_actual, 'consumable_min' => 20.0, 'status_alert' => $this->alertService->calculateAssetAlert($asset)])]);
    }

    public function update(UpdateConsumableRequest $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $asset = Asset::with(['categoria', 'consumable'])->findOrFail($id);
        if (! $asset->consumable) return response()->json(['success' => false, 'error' => 'Este activo no tiene configurado un insumo consumible.'], 422);
        if ($user->isEmployee() || (! $user->isLeader() && ! $user->isAdmin() && ! $user->canManageAssetSpecialty($asset))) return response()->json(['success' => false, 'error' => 'No autorizado para actualizar este consumible.'], 403);
        $oldLevel = $asset->consumable->nivel;
        $asset->consumable->nivel = $request->level;
        $asset->consumable->save();
        Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Nivel de consumible de {$asset->codigo} actualizado de {$oldLevel}% a {$request->level}% por {$user->nombre}."]);
        return response()->json(['success' => true, 'message' => "Nivel de {$asset->consumable->tipo} actualizado al {$request->level}%.", 'data' => ['asset_id' => $asset->id, 'consumable_level' => (float) $request->level, 'status_alert' => $this->alertService->calculateAssetAlert($asset->fresh('consumable'))]]);
    }
}
