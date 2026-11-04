<?php

namespace App\Policies;

use App\Models\User;

class StatsPolicy
{
    /**
     * Métricas globales de costos, presupuestos y ROI:
     * Exclusivo para Administrador y Técnico Líder (Jerarquías 1 y 2).
     */
    public function viewGlobal(User $user): bool
    {
        return $user->hasHierarchyAtLeast(2);
    }

    /**
     * Métricas operativas de su propia área:
     * Técnicos Especialistas.
     */
    public function viewSpecialty(User $user): bool
    {
        return $user->hasHierarchyAtLeast(3);
    }
}
