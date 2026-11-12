<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetConsumable;
use App\Models\Audit;
use App\Models\CalendarTask;
use App\Models\Report;
use App\Models\Location;
use App\Models\User;
use App\Services\AlertService;
use App\Services\FloorplanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssetController extends Controller
{
    public function __construct(private readonly AlertService $alertService) {}

    private function resolveUser(Request $request): ?User
    {
        return $request->user();
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        if (! Gate::forUser($user)->allows('viewAny', Asset::class)) {
            return response()->json(['success' => false, 'error' => 'Acceso denegado al inventario.', 'user_role' => $user->roleName()], 403);
        }
        $query = Asset::with(['categoria', 'ubicacionDetalle', 'consumable', 'mantenimientos'])->orderBy('id');
        $archive = [];
        if ($request->boolean('incluir_historico')) {
            [$retired, $inventory] = $query->get()->partition(fn (Asset $asset) => $asset->retirado);
            $archive = $retired->sortByDesc('fecha_baja')->map(fn (Asset $asset) => $this->serialize($asset))->values();
        } else {
            $inventory = $query->inInventory()->get();
        }
        $assets = $inventory->map(fn (Asset $asset) => $this->serialize($asset))->values();
        if ($request->filled('alerta')) $assets = $assets->where('status_alert', $request->alerta)->values();
        return response()->json(['success' => true, 'data' => $assets, 'total' => $assets->count(), 'historico' => $archive]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $asset = Asset::with(['categoria', 'ubicacionDetalle', 'consumable', 'mantenimientos', 'reportes.creadores'])->findOrFail($id);
        if (! Gate::forUser($user)->allows('view', $asset)) {
            return response()->json(['success' => false, 'error' => 'No posee autorización para este activo.', 'user_role' => $user->roleName()], 403);
        }
        $position = DB::table('asset_map_positions')->where('activo_id', $id)->first();
        if ($position && ($position->floor !== $asset->ubicacionDetalle?->piso || $position->room !== $asset->ubicacionDetalle?->sala)) $position = null;
        $reports = $asset->reportes->filter(fn ($report) => Gate::forUser($user)->allows('view', $report))->keyBy('id');
        $detail = array_merge($asset->toArray(), [
            'mantenimientos' => $asset->mantenimientos->map(fn ($item) => array_merge($item->toArray(),
                ['reporte_id' => $reports->has($item->reporte_id) ? $item->reporte_id : null])),
            'nombre' => $asset->nombre, 'codigo' => $asset->codigo, 'serie' => $asset->serie,
            'estado' => $asset->estado, 'responsable' => $asset->responsable,
            'ubicacion_id' => $this->locationIdFor($asset->ubicacionDetalle),
            'edit_version' => $this->editVersion($asset),
            'last_maintenance' => $asset->last_maintenance?->toDateString(),
            'next_maintenance' => $asset->next_maintenance?->toDateString(),
            'map_position' => $position ? ['x' => $position->x, 'z' => $position->z] : null,
            'ubicacion_detalle' => $asset->ubicacionDetalle ? array_merge($asset->ubicacionDetalle->toArray(), ['nombre' => $asset->ubicacionDetalle->nombre]) : null,
            'reportes' => $reports->sortByDesc('fecha_creacion')->map(fn ($report) => array_merge($report->toArray(), ['codigo' => $report->codigo]))->values(),
        ]);
        return response()->json(['success' => true, 'data' => ['asset' => $detail, 'status_alert' => $this->alertService->calculateAssetAlert($asset), 'days_to_maintenance' => $asset->days_to_maintenance]]);
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user || ! Gate::forUser($user)->allows('create', Asset::class)) return response()->json(['success' => false, 'error' => 'No autorizado para crear activos patrimoniales.'], 403);
        $data = $request->validated();
        $category = AssetCategory::findOrFail($data['categoria_id']);
        $sourceLocation = $this->locationFromId((int) $data['ubicacion_id']);
        $asset = DB::transaction(function () use ($data, $category, $sourceLocation, $user) {
            $piso = $sourceLocation['piso'];
            $sala = $sourceLocation['sala'];
            $asset = new Asset(['categoria_id' => $category->id, 'serial' => $data['serie'], 'ubicacion' => trim("{$piso} - {$sala}", ' -'), 'estado_activo' => 'Operativo', 'fecha_alta' => now(Report::TIME_ZONE)->toDateString(), 'frecuencia_service' => (int) ($data['frecuencia_service'] ?? 0)]);
            $this->setInventoryDetails($asset, $data);
            $asset->save();
            Location::create(['activo_id' => $asset->id, 'piso' => $piso, 'sala' => $sala]);
            if ($category->requiere_consumible) AssetConsumable::create(['activo_id' => $asset->id, 'nombre' => 'Consumible de '.$asset->codigo, 'tipo' => 'Insumo', 'nivel' => 100]);
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Alta de activo {$asset->codigo} por {$user->nombre}."]);
            return $asset;
        }, 3);
        return response()->json(['success' => true, 'message' => "Activo {$asset->codigo} dado de alta con éxito.", 'data' => $this->serialize($asset->load(['categoria', 'ubicacionDetalle', 'consumable']))], 201);
    }

    public function update(UpdateAssetRequest $request, Asset $asset): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $asset = DB::transaction(function () use ($data, $user, $asset) {
            $current = Asset::with('ubicacionDetalle')->lockForUpdate()->findOrFail($asset->id);
            abort_unless(Gate::forUser($user)->allows('update', $current), 403);
            abort_unless(hash_equals($this->editVersion($current), $data['expected_version']), 409,
                'El activo cambió desde que abrió el formulario. Vuelva a abrir la edición para revisar los datos actuales.');

            $before = $this->editableValues($current);
            if (isset($data['condicion_operativa'])) {
                abort_if($current->retirado, 409, 'Reincorpore el equipo antes de cambiar su condición.');
                $current->estado_activo = $data['condicion_operativa'];
            }
            $current->categoria_id = $data['categoria_id'];
            $current->serial = $data['serie'];
            $current->frecuencia_service = $data['frecuencia_service'] ?? $current->frecuencia_service;
            $this->setInventoryDetails($current, $data);
            if ($data['ubicacion_id'] !== null) {
                $location = $this->locationFromId((int) $data['ubicacion_id']);
                $current->ubicacion = trim("{$location['piso']} - {$location['sala']}", ' -');
                $current->ubicacionDetalle()->updateOrCreate(['activo_id' => $current->id], $location);
            }
            $current->save();

            $category = AssetCategory::findOrFail($current->categoria_id);
            if ($category->requiere_consumible && ! $current->consumibles()->exists()) {
                $current->consumibles()->create(['nombre' => 'Consumible de '.$current->codigo, 'tipo' => 'Insumo', 'nivel' => 100]);
            }
            // Los consumibles existentes mantienen su lectura aunque cambie la categoría.
            // El mapa detecta como obsoleta una posición cuya sala ya no coincide.
            $current->load(['categoria', 'ubicacionDetalle', 'consumable']);
            $changes = [];
            foreach ($this->editableValues($current) as $field => $value) {
                if ($before[$field] !== $value) $changes[$field] = ['antes' => $before[$field], 'despues' => $value];
            }
            if ($changes) {
                Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Edición de activo {$current->codigo} por {$user->nombre}: ".json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            }

            return $current;
        }, 3);

        return response()->json(['success' => true, 'message' => "Activo {$asset->codigo} actualizado con éxito.", 'data' => $this->serialize($asset)]);
    }

    private function setInventoryDetails(Asset $asset, array $data): void
    {
        $name = $data['responsable'] ?? null;
        $ownerId = $data['responsable_id'] ?? null;
        if (! array_key_exists('responsable_id', $data)) {
            if ($asset->exists && (! array_key_exists('responsable', $data) || $name === $asset->responsable)) {
                $asset->setInventoryDetails($data['nombre'], $asset->responsable);
                return;
            }
            // Los clientes anteriores enviaban el nombre del responsable.
            if ($name !== null) {
                $ids = User::where('nombre_completo', $name)->limit(2)->pluck('id');
                if ($ids->count() > 1) {
                    throw ValidationException::withMessages(['responsable' => 'Hay varias cuentas con ese nombre. Seleccione la cuenta por su correo.']);
                }
                $ownerId = $ids->first();
            }
        }
        if ($ownerId !== null) {
            $owner = User::whereKey($ownerId)->sharedLock()->first();
            if (! $owner || (! $owner->activo && ! ($asset->retirado && $asset->responsable_id == $ownerId))) {
                throw ValidationException::withMessages(['responsable_id' => 'Seleccione una cuenta activa.']);
            }
            $name = $owner->nombre;
        }
        $asset->responsable_id = $ownerId;
        $asset->setInventoryDetails($data['nombre'], $name);
    }

    private function editableValues(Asset $asset): array
    {
        return ['estado_activo' => $asset->estado_activo, 'categoria_id' => (int) $asset->categoria_id, 'serie' => $asset->serial,
            'nombre' => $asset->nombre, 'responsable' => $asset->responsable, 'responsable_id' => $asset->responsable_id,
            'ubicacion' => $asset->ubicacion, 'piso' => $asset->ubicacionDetalle?->piso,
            'sala' => $asset->ubicacionDetalle?->sala, 'frecuencia_service' => $asset->frecuencia_service];
    }

    private function editVersion(Asset $asset): string
    {
        return hash('sha256', json_encode([$asset->id, $this->editableValues($asset), $asset->estado_activo,
            $asset->descripcion, $asset->getRawOriginal('updated_at')], JSON_THROW_ON_ERROR));
    }

    public function changeRetirement(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (! $user) return response()->json(['success' => false, 'error' => 'No autenticado.'], 401);
        $request->validate(['expected_state' => 'sometimes|required|string|max:255']);
        $asset = DB::transaction(function () use ($request, $user, $id) {
            $asset = Asset::lockForUpdate()->findOrFail($id);
            abort_unless(Gate::forUser($user)->allows('toggleActive', $asset), 403, 'No autorizado para dar de baja activos.');
            // DELETE siempre archiva, incluso si el cliente reintenta una baja confirmada.
            if ($request->isMethod('delete') && $asset->retirado) return $asset;
            abort_if($request->has('expected_state') && $request->expected_state !== $asset->estado_activo, 409, 'El estado del activo cambió. Actualice los datos antes de repetir la acción.');
            $previous = $asset->estado_activo;
            if ($asset->retirado) {
                // Los retiros antiguos sin antecedente no prueban que el equipo funcione.
                $saved = $asset->estado_previo_retiro;
                $asset->estado_activo = $saved && mb_strtolower(trim($saved)) !== 'retirado' ? $saved : 'Fuera de Servicio';
                $asset->estado_previo_retiro = null;
                $asset->fecha_baja = null;
                if ($asset->responsable_id && ! User::whereKey($asset->responsable_id)->sharedLock()->value('activo')) {
                    $asset->responsable_id = null;
                    $asset->setInventoryDetails($asset->nombre, null);
                }
            } else {
                $visits = CalendarTask::where('asset_id', $id)->where('completed', false)->whereNull('cancel_reason');
                $cancelled = $visits->update(['cancel_reason' => 'Retiro del dispositivo.', 'revision' => DB::raw('revision + 1')]);
                if ($cancelled) Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Retiro de {$asset->codigo}: {$cancelled} tareas canceladas por {$user->nombre}."]);
                $asset->estado_previo_retiro = $previous;
                $asset->estado_activo = 'Retirado';
                $asset->fecha_baja = now();
            }
            $asset->save();
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Estado del activo {$asset->codigo} actualizado por {$user->nombre}: {$previous} → {$asset->estado_activo}. Historial conservado."]);
            return $asset;
        }, 3);
        return response()->json(['success' => true, 'message' => "{$asset->codigo}: {$asset->estado_activo}. Se conservó el historial.", 'data' => $asset]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => AssetCategory::withCount(['activos' => fn ($query) => $query->inInventory()])->get()]);
    }

    public function floorplan(Request $request, FloorplanService $floorplan): JsonResponse
    {
        $user = $this->resolveUser($request);
        abort_unless($user && Gate::forUser($user)->allows('viewAny', Asset::class), 403);
        $assets = Asset::inInventory()->with(['categoria', 'ubicacionDetalle', 'consumable', 'mantenimientos', 'reportes.creadores'])->orderBy('id')->get();
        return response()->json(['success' => true, 'data' => $floorplan->build($assets, $user, fn ($asset) => $this->serialize($asset))]);
    }

    public function saveMapPosition(Request $request, int $id): JsonResponse
    {
        $user = $this->resolveUser($request);
        $asset = Asset::findOrFail($id);
        abort_unless($user && Gate::forUser($user)->allows('update', $asset), 403);
        $data = $request->validate([
            'x' => ['required', 'numeric', 'between:0.05,0.95'],
            'z' => ['required', 'numeric', 'between:0.05,0.95'],
            'elevation' => ['required', 'numeric', 'between:0,4'],
            'rotation' => ['required', 'numeric', 'between:0,360'],
            'floor' => ['required', 'string', 'max:255'],
            'room' => ['required', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($id, $data, $user): void {
            $asset = Asset::with('ubicacionDetalle')->lockForUpdate()->findOrFail($id);
            abort_if($asset->retirado, 409, 'El dispositivo fue retirado del mapa.');
            $location = $asset->ubicacionDetalle;
            abort_if(! $location || $data['floor'] !== $location->piso || $data['room'] !== $location->sala, 409,
                'La ubicación del inventario cambió o no está registrada. Actualiza el mapa antes de guardar.');
            DB::table('asset_map_positions')->updateOrInsert(['activo_id' => $id], array_merge($data, ['updated_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]));
            Audit::create(['fecha_auditoria' => now(), 'descripcion' => "Posición de mapa del activo {$id} confirmada por {$user->nombre}."]);
        });
        return response()->json(['success' => true]);
    }

    public function locations(): JsonResponse
    {
        $locations = Location::mapRooms()->sortKeys()
            ->map(fn (array $room, int $id) => [
                'id' => $id,
                'name' => "{$room['floor']} - {$room['location']}",
                'sector' => $room['floor'],
                'sala' => $room['location'],
                'map_label' => $room['label'],
            ])->values();

        return response()->json(['success' => true, 'data' => $locations]);
    }

    private function serialize(Asset $asset): array
    {
        return ['id' => $asset->id, 'code' => $asset->codigo, 'serial' => $asset->serial, 'name' => $asset->nombre, 'category_id' => $asset->categoria_id, 'category_name' => $asset->categoria?->nombre, 'location_id' => $this->locationIdFor($asset->ubicacionDetalle), 'location_name' => $asset->ubicacionDetalle?->nombre ?? $asset->ubicacion, 'responsible_id' => $asset->responsable_id, 'responsible' => $asset->responsable, 'state' => $asset->estado, 'active' => $asset->activo, 'retired_at' => $asset->fecha_baja?->toIso8601String(), 'registered_on' => $asset->fecha_alta?->toDateString(), 'periodicity_days' => $asset->frecuencia_service, 'last_maintenance' => $asset->last_maintenance?->toDateString(), 'next_maintenance' => $asset->next_maintenance?->toDateString(), 'days_to_maintenance' => $asset->days_to_maintenance, 'status_alert' => $this->alertService->calculateAssetAlert($asset), 'edit_version' => $this->editVersion($asset), 'has_consumable' => $asset->consumable !== null || (bool) $asset->categoria?->requiere_consumible, 'consumable_type' => $asset->consumable?->tipo, 'consumable_level' => $asset->consumable?->nivel_actual, 'consumable_min' => 20.0];
    }

    private function locationFromId(int $id): array
    {
        $room = Location::mapRooms()->get($id);
        return ['piso' => $room['floor'], 'sala' => $room['location']];
    }

    private function locationIdFor(?Location $location): ?int
    {
        if (! $location) return null;

        foreach (Location::mapRooms() as $id => $room) {
            if ($location->piso === $room['floor'] && $location->sala === $room['location']) {
                return $id;
            }
        }

        return null;
    }
}
