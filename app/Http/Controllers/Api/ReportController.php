<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\AssignReportRequest;
use App\Http\Requests\TransitionReportRequest;
use App\Models\Report;
use App\Models\TechnicalChief;
use App\Models\Technician;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    private function user(Request $request): ?User
    {
        return $request->user();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $query = Report::with(['activos.categoria', 'creadores.nombres']);
        if (! $user->hasHierarchyAtLeast(2)) {
            $query->where(function ($query) use ($user) {
                $query->whereHas('creadores', fn ($q) => $q->whereKey($user->id));
                if ($user->isTechnician()) $query->orWhereIn('id', DB::table('tecnico_jefe_tecnico')->select('reporte_id')->where('tecnico_id', $user->id));
            });
        }
        if ($request->filled('prioridad')) {
            $urgency = match ($request->prioridad) {'Critica', 'Crítica' => 'Critico', 'Alta' => 'Alto', 'Baja' => 'Bajo', default => 'Medio'};
            $query->where('urgencia', $urgency);
        }
        $reports = $query->orderByDesc('fecha_creacion')->get()->map(fn (Report $report) => $this->serialize($report));
        return response()->json(['success' => true, 'data' => $reports, 'total' => $reports->count()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $report = Report::with(['activos.categoria', 'creadores.nombres'])->findOrFail($id);
        if (! Gate::forUser($user)->allows('view', $report)) return response()->json(['success' => false, 'error' => "No posee autorización para consultar el reporte {$report->codigo}."], 403);
        return response()->json(['success' => true, 'data' => $this->serialize($report)]);
    }

    public function availableAssets(): JsonResponse
    {
        // Minimal catalog for reporting a fault; it does not expose inventory details.
        $assets = \App\Models\Asset::with('categoria')->where('estado_activo', '!=', 'Retirado')->orderBy('id')->get();
        return response()->json(['success' => true, 'data' => $assets->map(fn ($asset) => [
            'id' => $asset->id, 'code' => $asset->codigo, 'name' => $asset->nombre,
            'category_name' => $asset->categoria?->nombre ?? 'Otros dispositivos',
            'location_name' => $asset->ubicacion, 'active' => true,
        ])]);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        if (! Gate::forUser($user)->allows('create', Report::class)) return response()->json(['success' => false, 'error' => 'No autorizado para crear reportes.'], 403);
        try {
            $report = $this->reportService->createReport((int) $request->asset_id, $user, $request->priority, $request->description);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'error' => $exception->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => "Reporte {$report->codigo} registrado y alerta generada.", 'data' => $this->serialize($report)], 201);
    }

    /** The chief, and only the chief, decides the technical assignment. */
    public function assign(AssignReportRequest $request, int $id): JsonResponse
    {
        $chief = $this->user($request);
        if (! $chief || ! $chief->isLeader()) {
            return response()->json(['success' => false, 'error' => 'Solo el Jefe Técnico puede asignar reportes.'], 403);
        }

        $report = Report::findOrFail($id);
        $report = $this->reportService->assignTechnician(
            $report,
            $chief,
            (int) $request->technician_id,
            $request->input('reassignment_reason'),
        );

        return response()->json(['success' => true, 'message' => 'Asignación técnica registrada.', 'data' => $this->serialize($report)]);
    }

    /** The workflow is managed exclusively by the chief. */
    public function transition(TransitionReportRequest $request, int $id): JsonResponse
    {
        $chief = $this->user($request);
        if (! $chief || ! $chief->isLeader()) {
            return response()->json(['success' => false, 'error' => 'Solo el Jefe Técnico puede cambiar el estado de un reporte.'], 403);
        }

        $report = $this->reportService->transition(
            Report::findOrFail($id),
            $chief,
            $request->status,
            $request->input('action_report'),
            $request->input('final_diagnosis'),
        );

        return response()->json(['success' => true, 'message' => "Reporte actualizado a {$report->estado_reporte}.", 'data' => $this->serialize($report)]);
    }

    public function technicians(Request $request): JsonResponse
    {
        $chief = $this->user($request);
        if (! $chief || ! $chief->isLeader()) {
            return response()->json(['success' => false, 'error' => 'Solo el Jefe Técnico puede consultar técnicos para una asignación.'], 403);
        }

        $chiefIds = TechnicalChief::query()->pluck('id');
        $technicians = User::with('nombres')
            ->whereIn('id', Technician::query()->whereNotIn('id', $chiefIds)->pluck('id'))
            ->where('activo', true)
            ->orderBy('id')
            ->get()
            ->map(fn (User $technician) => [
                'id' => $technician->id,
                'name' => $technician->nombre,
                'specialty' => $technician->especialidad,
            ]);

        return response()->json(['success' => true, 'data' => $technicians]);
    }

    private function serialize(Report $report): array
    {
        $activeAssignment = DB::table('tecnico_jefe_tecnico')
            ->where('reporte_id', $report->id)
            ->whereNull('fecha_finalizacion')
            ->orderByDesc('fecha_asignacion')
            ->first();
        $assignmentHistory = DB::table('tecnico_jefe_tecnico')
            ->where('reporte_id', $report->id)
            ->orderBy('fecha_asignacion')
            ->get()
            ->map(function (object $assignment): array {
                $technician = User::with('nombres')->find($assignment->tecnico_id);
                return [
                    'technician_id' => $assignment->tecnico_id,
                    'technician_name' => $technician?->nombre ?? "Técnico {$assignment->tecnico_id}",
                    'assigned_at' => $assignment->fecha_asignacion,
                    'finished_at' => $assignment->fecha_finalizacion,
                    'reassignment_reason' => $assignment->motivo_reasignacion,
                ];
            })->values();
        $assignedTechnician = $activeAssignment ? User::with('nombres')->find($activeAssignment->tecnico_id) : null;

        return [
            'id' => $report->id,
            'code' => $report->codigo,
            'asset_id' => $report->activo_id,
            'asset_code' => $report->activo?->codigo,
            'asset_name' => $report->activo?->nombre,
            'asset_category' => $report->activo?->categoria?->nombre,
            'creator_id' => $report->creador_id,
            'creator_name' => $report->creador?->nombre,
            'creator_role' => $report->creador?->role_title,
            'priority' => $report->prioridad,
            'description' => $report->descripcion,
            'status' => $report->estado_reporte,
            'action_report' => $report->informe_accionar,
            'final_diagnosis' => $report->diagnostico_final,
            'closed_at' => $report->fecha_cierre?->format('Y-m-d H:i'),
            'assigned_technician' => $assignedTechnician ? [
                'id' => $assignedTechnician->id,
                'name' => $assignedTechnician->nombre,
                'specialty' => $assignedTechnician->especialidad,
                'assigned_at' => $activeAssignment->fecha_asignacion,
            ] : null,
            'assignment_history' => $assignmentHistory,
            'created_at' => $report->fecha_creacion?->format('Y-m-d H:i'),
            'updated_at' => $report->updated_at?->format('Y-m-d H:i'),
        ];
    }
}
