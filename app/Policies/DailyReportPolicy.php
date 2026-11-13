<?php

namespace App\Policies;

use App\Models\DailyReport;
use App\Models\User;

class DailyReportPolicy
{
    public function view(User $u, DailyReport $r): bool
    {
        return $u->is_active && DailyReport::visibleTo($u)->whereKey($r->id)->exists();
    }

    public function create(User $u): bool
    {
        return $u->is_active && in_array($u->role, ['super_admin', 'admin', 'musyrif', 'musyrifah']);
    }

    public function update(User $u, DailyReport $r): bool
    {
        return $u->is_active && ($u->isAdmin() || ($u->isPembina() && $r->user_id === $u->id && $r->status !== 'verified'));
    }

    public function review(User $u, DailyReport $r): bool
    {
        return $this->view($u, $r) && in_array($u->role, ['super_admin', 'admin', 'koordinator']) && $r->status === 'submitted';
    }

    public function reopen(User $u, DailyReport $r): bool
    {
        return $u->is_active && $u->isAdmin() && $r->status === 'verified';
    }
}
