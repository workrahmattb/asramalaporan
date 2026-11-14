<?php

namespace App\Policies;

use App\Models\Santri;
use App\Models\User;

class SantriPolicy
{
    public function view(User $user, Santri $santri): bool
    {
        return $user->is_active && Santri::visibleTo($user)->whereKey($santri->id)->exists();
    }

    public function manage(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function addNote(User $user, Santri $santri): bool
    {
        return $this->view($user, $santri) && $santri->is_active && $santri->currentPlacement !== null && ($user->isAdmin() || $user->isPembina());
    }
}
