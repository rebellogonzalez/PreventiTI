<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FloorplanService
{
    public function build(Collection $assets, User $user, callable $serialize): array
    {
        $rooms = collect(config('floorplan.rooms'));
        $positions = DB::table('asset_map_positions')->get()->keyBy('activo_id');
        $records = $assets->map(function (Asset $asset) use (&$rooms, $positions, $user, $serialize) {
            $location = $asset->ubicacionDetalle;
            $floor = $location?->piso ?? '';
            $name = $location?->sala ?? $asset->ubicacion ?? '';
            $room = $rooms->first(fn ($room) => $room['floor'] === $floor && $room['location'] === $name);
            if (! $room) {
                // New/unmapped locations remain visible instead of silently dropping assets.
                $room = ['id' => 'location-'.substr(hash('sha256', $floor.'|'.$name), 0, 12), 'floor' => $floor, 'location' => $name,
                    'label' => $name ?: 'Sin ubicación registrada', 'purpose' => 'Espacio pendiente de configurar',
                    'x' => 30, 'z' => ($rooms->count() - count(config('floorplan.rooms'))) * 10,
                    'width' => 12, 'depth' => 9, 'color' => '#e2e8f0', 'unconfigured' => true];
                $rooms->push($room);
            }
            $saved = $positions->get($asset->id);
            $valid = $saved && $saved->floor === $floor && $saved->room === $name;
            $reports = $asset->reportes;
            $activeReports = $reports->filter(fn ($r) => ! in_array($r->estado_reporte, ['Finalizado', 'Resuelto', 'Cerrado', 'Cancelado'], true));
            $record = $serialize($asset);
            $state = mb_strtolower($asset->estado_activo);
            $status = match (true) {
                in_array($state, ['fuera de servicio', 'retirado']) => 'offline',
                $state === 'desconectado' => 'disconnected',
                $activeReports->isNotEmpty() => 'incident',
                in_array($record['status_alert'], ['critica', 'atencion']) || $state === 'en mantenimiento' => 'warning',
                $state === 'operativo' => 'operational',
                default => 'unknown',
            };
            return array_merge($record, [
                'room_id' => $room['id'], 'operational_status' => $status,
                'active_incidents' => $activeReports->count(),
                'reports' => $activeReports->filter(fn ($r) => Gate::forUser($user)->allows('view', $r))->map(fn ($r) => [
                    'id' => $r->id, 'code' => $r->codigo, 'state' => $r->estado_reporte,
                    'description' => $r->descripcion, 'urgency' => $r->urgencia,
                ])->values(),
                'can_view_detail' => Gate::forUser($user)->allows('view', $asset),
                'position' => $valid ? ['x' => $saved->x, 'z' => $saved->z, 'elevation' => $saved->elevation, 'rotation' => $saved->rotation, 'source' => 'confirmed'] : null,
                'position_stale' => (bool) ($saved && ! $valid),
            ]);
        });
        return ['version' => config('floorplan.version'), 'name' => config('floorplan.name'),
            'schematic' => config('floorplan.schematic'), 'rooms' => $rooms->values(), 'assets' => $records->values(),
            'can_edit_positions' => $user->isAdmin(), 'updated_at' => now()->toIso8601String()];
    }
}
