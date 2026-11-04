<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    private function user(Request $request): ?User
    {
        return $request->user();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        if (! $user->isLeader()) return response()->json(['success' => false, 'error' => 'Solo el Jefe Técnico puede consultar alertas de reportes.'], 403);
        $query = Alert::with('activos');
        if ($request->boolean('unread_only')) $query->where('estado_alerta', '!=', 'Leída');
        $alerts = $query->orderByDesc('fecha_generacion')->get()->map(fn (Alert $alert) => $this->serialize($alert));
        return response()->json(['success' => true, 'data' => $alerts, 'unread' => $alerts->where('status', '!=', 'Leída')->count()]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        if (! $user->isLeader()) return response()->json(['success' => false, 'error' => 'Solo el Jefe Técnico puede actualizar alertas de reportes.'], 403);
        $alert = Alert::with('activos')->findOrFail($id);
        $alert->estado_alerta = 'Leída';
        $alert->save();
        return response()->json(['success' => true, 'data' => $this->serialize($alert)]);
    }

    private function serialize(Alert $alert): array
    {
        $asset = $alert->activos->first();
        $report = $alert->reporte_id ? Report::find($alert->reporte_id) : ($asset ? Report::whereHas('activos', fn ($q) => $q->whereKey($asset->id))->where('fecha_creacion', $alert->fecha_generacion)->first() : null);
        return ['id' => $alert->id, 'report_id' => $report?->id, 'title' => $alert->tipo_alerta, 'message' => "Alerta {$alert->nivel_alerta} para ".($asset?->codigo ?? 'activo sin asignar').'.', 'status' => $alert->estado_alerta, 'read_at' => $alert->estado_alerta === 'Leída' ? $alert->updated_at?->format('Y-m-d H:i') : null, 'created_at' => $alert->fecha_generacion?->format('Y-m-d H:i'), 'report' => $report ? ['id' => $report->id, 'code' => $report->codigo, 'asset_code' => $asset?->codigo, 'asset_name' => $asset?->nombre, 'creator_name' => $report->creador?->nombre, 'priority' => $report->prioridad, 'description' => $report->descripcion] : null];
    }
}
