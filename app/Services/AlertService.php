<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Report;
use Carbon\Carbon;

class AlertService
{
    public function calculateAssetAlert(Asset $asset): string
    {
        if (! $asset->activo || strtolower($asset->estado) === 'fuera de servicio') {
            return 'critica';
        }

        if ($asset->next_maintenance) {
            $days = (int) Carbon::today()->diffInDays($asset->next_maintenance->copy()->startOfDay(), false);
            if ($days < 0) {
                return 'critica';
            }
            if ($days <= 7) {
                return 'atencion';
            }
        }

        return $asset->consumable && $asset->consumable->nivel_actual <= 20 ? 'atencion' : 'normal';
    }

    public function getDashboardSummary(?string $specialty = null): array
    {
        $query = Asset::with(['categoria', 'ubicacionDetalle', 'consumable']);
        if ($specialty) {
            $query->whereHas('categoria', fn ($q) => $q->where('nombre', 'like', "%{$specialty}%"));
        }
        $assets = $query->get();
        $states = $assets->map(fn (Asset $asset) => $this->calculateAssetAlert($asset));

        $reports = Report::query();
        if ($specialty) {
            $reports->whereHas('activos.categoria', fn ($q) => $q->where('nombre', 'like', "%{$specialty}%"));
        }

        return [
            'total_activos' => $assets->count(),
            'normales' => $states->filter(fn ($state) => $state === 'normal')->count(),
            'atencion' => $states->filter(fn ($state) => $state === 'atencion')->count(),
            'criticas' => $states->filter(fn ($state) => $state === 'critica')->count(),
            'reportes_registrados' => $reports->count(),
        ];
    }
}
