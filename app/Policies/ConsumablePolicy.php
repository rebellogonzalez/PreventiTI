<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class ConsumablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasHierarchyAtLeast(3); // Admin, Líder, Técnicos
    }

    public function update(User $user, Asset $asset): bool
    {
        if ($user->isAdmin() || $user->isLeader()) {
            return true;
        }

        if ($user->isTechnician()) {
            return $user->canManageAssetSpecialty($asset);
        }

        return false;
    }
}
