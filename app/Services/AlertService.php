<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Report;
use App\Models\User;

class AlertService
{
    public function calculateAssetAlert(Asset $asset): string
    {
        return $asset->status_alert;
    }

    public function getDashboardSummary(User $user): array
    {
        $assets = Asset::inInventory()->with(['mantenimientos', 'consumible'])->get();
        $states = $assets->map(fn (Asset $asset) => $this->calculateAssetAlert($asset));

        $reports = Report::visibleTo($user);

        return [
            'total_activos' => $assets->count(),
            'normales' => $states->filter(fn ($state) => $state === 'normal')->count(),
            'atencion' => $states->filter(fn ($state) => $state === 'atencion')->count(),
            'criticas' => $states->filter(fn ($state) => $state === 'critica')->count(),
            'reportes_registrados' => $reports->count(),
        ];
    }
}
