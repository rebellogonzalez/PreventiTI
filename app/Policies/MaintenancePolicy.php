<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class MaintenancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasHierarchyAtLeast(3); // Admin, Líder, Técnicos
    }

    public function create(User $user, ?Asset $asset = null): bool
    {
        if ($user->isAdmin() || $user->isLeader()) {
            return true;
        }

        if ($user->isTechnician()) {
            if ($asset) {
                return $user->canManageAssetSpecialty($asset);
            }
            return true;
        }

        return false;
    }
}
