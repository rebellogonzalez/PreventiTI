<?php

namespace App\Services;

use App\Models\AssetCategory;
use App\Models\CalendarTask;
use App\Models\Report;
use App\Models\TechnicalChief;
use App\Models\Technician;
use App\Models\User;

class StatsService
{
    public function getStatsForUser(User $user): array
    {
        $isGlobal = $user->isAdmin() || $user->isLeader();
        $specialty = $user->especialidad;
        $categories = AssetCategory::withCount('activos');
        if (! $isGlobal && $specialty) {
            $categories->where('nombre', 'like', "%{$specialty}%");
        }

        $reports = Report::query();
        if (! $isGlobal && $specialty) {
            $reports->whereHas('activos.categoria', fn ($q) => $q->where('nombre', 'like', "%{$specialty}%"));
        }

        $ranking = [];
        if ($isGlobal) {
            $technicians = Technician::whereNotIn('id', TechnicalChief::query()->select('id'))->orderBy('id')->get();
            $techUsers = User::with('nombres')->whereIn('id', $technicians->modelKeys())->get()->keyBy('id');
            foreach ($technicians as $technician) {
                $techUser = $techUsers->get($technician->id);
                if ($techUser) {
                    $ranking[] = [
                        'id' => $techUser->id,
                        'name' => $techUser->nombre,
                        'title' => $techUser->role_title,
                        'role' => $techUser->roleName(),
                        'specialty' => $technician->tipo_tecnico,
                        'active' => $techUser->activo,
                        'maintenances' => CalendarTask::where('technician_id', $techUser->id)->where('tipo', 'mantenimiento')->count(),
                    ];
                }
            }
        }

        $costs = $isGlobal ? app(CostStatisticsService::class)->summarize() : [];

        return [
            'is_global' => $isGlobal,
            'user_role' => $user->roleName(),
            'user_specialty' => $specialty,
            'categories_distribution' => $categories->get()->map(fn ($category) => ['id' => $category->id, 'name' => $category->nombre, 'count' => $category->activos_count]),
            'reports_by_priority' => [
                'baja' => (clone $reports)->where('urgencia', 'Bajo')->count(),
                'media' => (clone $reports)->where('urgencia', 'Medio')->count(),
                'alta' => (clone $reports)->where('urgencia', 'Alto')->count(),
                'critica' => (clone $reports)->where('urgencia', 'Critico')->count(),
            ],
            'technicians_ranking' => $ranking,
            'budget' => $costs['budget'] ?? null,
            'components_replaced' => $costs['components_replaced'] ?? [],
            'recurring_incidents' => $costs['recurring_incidents'] ?? [],
            'maintenance_months' => $costs['maintenance_months'] ?? [],
        ];
    }
}
