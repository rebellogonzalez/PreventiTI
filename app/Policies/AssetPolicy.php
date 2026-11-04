<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    /**
     * Determina si el usuario puede listar el inventario patrimonial.
     * Solo Administrador, Técnico Líder y Técnicos Especialistas (RF-03).
     * Empleados tienen restringido el inventario general.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasHierarchyAtLeast(3);
    }

    /**
     * Prevención IDOR: Determina si el usuario puede consultar la ficha de un activo específico.
     * - Administrador y Técnico Líder: Cualquier activo.
     * - Técnico Especialista: Inventario técnico compartido; el modelo no asigna
     *   categorías de activos a especialidades.
     * - Empleado: Sin acceso a fichas del inventario.
     */
    public function view(User $user, Asset $asset): bool
    {
        if ($user->isAdmin() || $user->isLeader()) {
            return true;
        }

        if ($user->isTechnician()) {
            return $user->canManageAssetSpecialty($asset);
        }

        return false;
    }

    /**
     * Determina si el usuario puede dar de alta un activo (RF-02).
     * Exclusivo para Administrador (Jerarquía 1).
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determina si el usuario puede editar datos patrimoniales de un activo (RF-02).
     * Exclusivo para Administrador.
     */
    public function update(User $user, Asset $asset): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determina si el usuario puede aplicar baja lógica a un activo (RF-02 / CP-02).
     * Exclusivo para Administrador.
     */
    public function toggleActive(User $user, Asset $asset): bool
    {
        return $user->isAdmin();
    }

    /**
     * La baja física es excepcional: solo la puede solicitar el Jefe Técnico
     * y únicamente cuando el activo no posee historial que conservar.
     */
    public function purge(User $user, Asset $asset): bool
    {
        return $user->isLeader();
    }
}
