<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\AssignReportRequest;
use App\Http\Requests\TransitionReportRequest;
use App\Models\Asset;
use App\Models\Report;
use App\Models\Maintenance;
use App\Models\TechnicalChief;
use App\Models\Technician;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Services\ReportReadService;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService, private readonly ReportReadService $reader) {}

    private function user(Request $request): ?User
    {
        return $request->user();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $query = $this->reader->query($user);
        if ($request->filled('prioridad')) {
            $urgency = match ($request->prioridad) {'Critica', 'Crítica' => 'Critico', 'Alta' => 'Alto', 'Baja' => 'Bajo', default => 'Medio'};
            $query->where('urgencia', $urgency);
        }
        $reports = $query->orderByDesc('fecha_creacion')->orderByDesc('id')->get()->map(fn (Report $report) => $this->reader->record($report, $user, $request->boolean('resumen')));
        return response()->json(['success' => true, 'data' => $reports, 'total' => $reports->count()]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $report = Report::with(['activos.categoria', 'creadores.nombres'])->findOrFail($id);
        if (! Gate::forUser($user)->allows('view', $report)) return response()->json(['success' => false, 'error' => "No posee autorización para consultar el reporte {$report->codigo}."], 403);
        $data = $this->reader->record($report, $user);
        if ($user->canAccessArea('mantenimientos')) {
            $data['inspections'] = Maintenance::where('reporte_id', $id)->orderByDesc('fecha_mantenimiento')
                ->get()->map(fn ($item) => ['id' => $item->id, 'asset_id' => $item->activo_id, 'date' => $item->fecha_mantenimiento->toDateString()]);
        }
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function availableAssets(Request $request): JsonResponse
    {
        // Minimal catalog for reporting a fault; it does not expose inventory details.
        $assets = Asset::inInventory()->inReportAreasFor($request->user())->with(['categoria', 'ubicacionDetalle'])->orderBy('id')->get();
        return response()->json(['success' => true, 'data' => $assets->map(fn ($asset) => [
            'id' => $asset->id, 'code' => $asset->codigo, 'name' => $asset->nombre,
            'category_name' => $asset->categoria?->nombre ?? 'Otros dispositivos',
            'location_name' => $asset->ubicacionDetalle?->nombre ?? $asset->ubicacion, 'active' => true,
        ])]);
    }

    public function store(StoreReportRequest $request): JsonResponse
    {
        $user = $this->user($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        if (! Gate::forUser($user)->allows('create', Report::class)) return response()->json(['success' => false, 'error' => 'No autorizado para crear reportes.'], 403);
        $report = $this->reportService->createReport((int) $request->asset_id, $user, $request->priority,
            $request->description, $request->input('submission_id'));
        return response()->json(['success' => true, 'message' => "Reporte {$report->codigo} registrado y alerta generada.", 'data' => $this->reader->record($report, $user)], 201);
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
            $request->input('expected_version'),
        );

        return response()->json(['success' => true, 'message' => "Asignación guardada. Estado del reporte: {$report->estado_reporte}.", 'data' => $this->reader->record($report, $chief)]);
    }

    public function transition(TransitionReportRequest $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $report = $this->reportService->transition(
            Report::findOrFail($id),
            $actor,
            $request->status,
            $request->input('action_report'),
            $request->input('final_diagnosis'),
            $request->input('expected_version'),
        );

        return response()->json(['success' => true, 'message' => "Reporte actualizado a {$report->estado_reporte}.", 'data' => $this->reader->record($report, $actor)]);
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

    public function history(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => 'sometimes|integer|min:1|max:1000000', 'sort' => 'nullable|in:oldest,newest',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d',
            'query' => 'nullable|string|max:2000', 'asset' => 'nullable|string|max:150',
            'status' => 'nullable|string|max:50', 'technician' => 'nullable|string|max:32',
        ]);
        return response()->json($this->reader->history($request->user(), $filters));
    }
}
