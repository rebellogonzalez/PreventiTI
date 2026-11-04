<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\Audit;
use App\Models\CalendarTask;
use App\Models\User;
use App\Services\AlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct(private readonly AlertService $alertService) {}

    private function user(Request $request): ?User
    {
        return $request->user();
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        if ($user->isEmployee()) return response()->json(['success' => true, 'data' => ['reportes_propios' => $user->reportesCreados()->count()]]);
        $summary = $this->alertService->getDashboardSummary($user->isTechnician() && ! $user->isLeader() && ! $user->isAdmin() ? $user->especialidad : null);
        $summary['notificaciones_no_leidas'] = $user->isAdmin() || $user->isLeader() ? Alert::where('estado_alerta', '!=', 'Leída')->count() : 0;
        return response()->json(['success' => true, 'data' => $summary]);
    }

    public function semaforo(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $groups = ['normal' => [], 'atencion' => [], 'critica' => []];
        foreach (Asset::with(['categoria', 'ubicacionDetalle', 'consumable'])->get() as $asset) {
            $state = $this->alertService->calculateAssetAlert($asset);
            $groups[$state][] = ['id' => $asset->id, 'codigo' => $asset->codigo, 'nombre' => $asset->nombre, 'categoria' => $asset->categoria?->nombre, 'ubicacion' => $asset->ubicacionDetalle?->nombre ?? $asset->ubicacion, 'responsable' => $asset->responsable, 'estado' => $asset->estado, 'days_to_maintenance' => $asset->days_to_maintenance, 'status_alert' => $state, 'consumable_level' => $asset->consumable?->nivel_actual];
        }
        return response()->json(['success' => true, 'data' => [...$groups, 'counts' => ['normal' => count($groups['normal']), 'atencion' => count($groups['atencion']), 'critica' => count($groups['critica']), 'total' => array_sum(array_map('count', $groups))]]]);
    }

    public function todayTasks(Request $request): JsonResponse
    {
        if (! $this->user($request)) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $query = CalendarTask::where('fecha', $request->get('date', now()->toDateString()));
        if (! $request->user()->hasHierarchyAtLeast(2)) $query->where('technician_id', $request->user()->id);
        return response()->json(['success' => true, 'data' => $query->orderBy('hora')->get()]);
    }

    public function criticalConsumables(Request $request): JsonResponse
    {
        if (! $this->user($request)) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $assets = Asset::whereHas('consumible', fn ($q) => $q->where('nivel', '<=', 20))->with(['ubicacionDetalle', 'consumable'])->get();
        return response()->json(['success' => true, 'data' => $assets->map(fn (Asset $asset) => ['id' => $asset->id, 'codigo' => $asset->codigo, 'nombre' => $asset->nombre, 'location_name' => $asset->ubicacionDetalle?->nombre ?? $asset->ubicacion, 'consumable_type' => $asset->consumable?->tipo, 'consumable_level' => $asset->consumable?->nivel_actual, 'consumable_min' => 20.0, 'status_alert' => $this->alertService->calculateAssetAlert($asset)])]);
    }

    public function upcomingMaintenances(Request $request): JsonResponse
    {
        if (! $this->user($request)) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $assets = Asset::with(['categoria', 'ubicacionDetalle', 'mantenimientos'])->get()->filter(fn (Asset $asset) => $asset->activo && $asset->next_maintenance)->sortBy('next_maintenance')->take(6)->values();
        return response()->json(['success' => true, 'data' => $assets->map(fn (Asset $asset) => ['id' => $asset->id, 'codigo' => $asset->codigo, 'nombre' => $asset->nombre, 'categoria' => $asset->categoria?->nombre, 'ubicacion' => $asset->ubicacionDetalle?->nombre ?? $asset->ubicacion, 'next_maintenance' => $asset->next_maintenance?->toDateString(), 'days_to_maintenance' => $asset->days_to_maintenance, 'status_alert' => $this->alertService->calculateAssetAlert($asset)])]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        if (! $user->hasHierarchyAtLeast(2)) return response()->json(['success' => false, 'error' => 'Acceso denegado a la auditoría.'], 403);
        return response()->json(['success' => true, 'data' => Audit::orderByDesc('fecha_auditoria')->limit(10)->get()]);
    }
}
