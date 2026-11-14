<?php

namespace App\Policies;

use App\Models\Santri;
use App\Models\SantriAssessment;
use App\Models\User;

class SantriAssessmentPolicy
{
    public function view(User $user, SantriAssessment $assessment): bool
    {
        return $user->is_active && SantriAssessment::visibleTo($user)->whereKey($assessment->id)->exists();
    }

    public function update(User $user, SantriAssessment $assessment): bool
    {
        if (! $this->view($user, $assessment) || ! in_array($assessment->status, ['draft', 'revision'])) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isPembina() && $assessment->period->isOpen() && $assessment->santri->is_active && $assessment->santri->currentPlacement?->asrama_id === $assessment->asrama_id && Santri::visibleTo($user)->whereKey($assessment->santri_id)->exists();
    }

    public function review(User $user, SantriAssessment $assessment): bool
    {
        return $this->view($user, $assessment) && ($user->isAdmin() || $user->role === 'koordinator') && $assessment->status === 'submitted';
    }

    public function reopen(User $user, SantriAssessment $assessment): bool
    {
        return $this->view($user, $assessment) && $user->isAdmin() && $assessment->status === 'approved';
    }

    public function print(User $user, SantriAssessment $assessment): bool
    {
        return $this->view($user, $assessment) && $assessment->status === 'approved';
    }
}
