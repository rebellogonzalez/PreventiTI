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
use Illuminate\Support\Facades\DB;
use App\Models\CalendarTask;

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
        $assets = Asset::inInventory()->has('consumible')->with(['categoria', 'ubicacionDetalle', 'consumable'])->get();
        return response()->json(['success' => true, 'data' => $assets->map(fn (Asset $asset) => ['id' => $asset->id, 'code' => $asset->codigo, 'name' => $asset->nombre, 'category_name' => $asset->categoria?->nombre, 'location_name' => $asset->ubicacionDetalle?->nombre ?? $asset->ubicacion, 'consumable_type' => $asset->consumable?->tipo, 'consumable_level' => $asset->consumable?->nivel_actual, 'consumable_min' => 20.0, 'status_alert' => $this->alertService->calculateAssetAlert($asset)])]);
    }

    public function update(UpdateConsumableRequest $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        return DB::transaction(function () use ($user, $request, $id) {
            $asset = Asset::inInventory()->with('categoria')->lockForUpdate()->findOrFail($id);
            abort_unless($user->canManageAssetSpecialty($asset), 403);
            $insumo = $asset->consumible()->lockForUpdate()->first();
            abort_unless($insumo, 422, 'El activo no tiene un insumo configurado.');
            if ($request->filled('task_id')) {
                $task = CalendarTask::lockForUpdate()->findOrFail($request->integer('task_id'));
                $task->authorizeExecution($user);
                abort_unless($task->tipo === 'consumible' && (int) $task->asset_id === $id, 422, 'El insumo no corresponde a la tarea.');
                abort_if($task->completed, 409, 'La reposición ya se registró. Actualice la agenda.');
                $task->complete($user);
            }
            $oldLevel = $insumo->nivel;
            $insumo->update(['nivel' => $request->level]);
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Nivel de consumible de {$asset->codigo} actualizado de {$oldLevel}% a {$request->level}% por {$user->nombre}."]);
            return response()->json(['success' => true, 'message' => "Nivel actualizado al {$request->level}%.",
                'data' => ['asset_id' => $id, 'consumable_level' => (float) $request->level, 'status_alert' => $this->alertService->calculateAssetAlert($asset)]]);
        }, 3);
    }
}
