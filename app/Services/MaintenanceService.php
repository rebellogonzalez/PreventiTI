<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Audit;
use App\Models\Maintenance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    public function registerMaintenance(int $assetId, User $technician, string $date, string $detail): Maintenance
    {
        return DB::transaction(function () use ($assetId, $technician, $date, $detail) {
            $asset = Asset::findOrFail($assetId);
            $maintenanceDate = Carbon::parse($date)->startOfDay();
            $nextDate = $maintenanceDate->copy()->addDays($asset->frecuencia_service);
            $maintenance = Maintenance::create([
                'activo_id' => $asset->id,
                'fecha_mantenimiento' => $maintenanceDate->toDateString(),
                'descripcion' => $detail,
                'observaciones' => "Registrado por {$technician->nombre}. Próxima revisión calculada: {$nextDate->toDateString()}.",
            ]);
            Audit::create([
                'fecha_auditoria' => now(),
                'descripcion' => "Mantenimiento de {$asset->codigo} registrado por {$technician->nombre}.",
            ]);

            return $maintenance->load('activo.categoria');
        });
    }
}
