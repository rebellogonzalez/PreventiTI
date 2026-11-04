<?php

namespace App\Policies;

use App\Models\User;

class InfrastructurePolicy
{
    /**
     * Módulo de redes, escaneo Nmap y DRP:
     * Exclusivo para Técnico Líder (Ignacio de la Rosa) y Administradores (Jerarquías 1 y 2).
     */
    public function view(User $user): bool
    {
        return $user->hasHierarchyAtLeast(2);
    }

    public function scan(User $user): bool
    {
        return $user->hasHierarchyAtLeast(2);
    }

    public function backup(User $user): bool
    {
        return $user->hasHierarchyAtLeast(2);
    }
}
