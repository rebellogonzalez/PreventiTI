<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Report $report): bool
    {
        return $user->isAdmin()
            || $user->isLeader()
            || $report->creadores()->whereKey($user->id)->exists()
            || ($user->isTechnician() && \Illuminate\Support\Facades\DB::table('tecnico_jefe_tecnico')->where('reporte_id', $report->id)->where('tecnico_id', $user->id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->activo;
    }

    public function transition(User $user, Report $report, ?int $activeTechnicianId): bool
    {
        return $user->activo && ($user->isLeader() || (
            $user->roleName() === 'tecnico'
            && $user->id === $activeTechnicianId
            && ! $report->isClosed()
            && in_array($report->estado_reporte, ['En curso', 'Diagnóstico'], true)
        ));
    }
}
