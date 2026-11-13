<?php

namespace App\Livewire\Reports;

class ReportMonitoring extends ReportHistory
{
    public function mount(): void
    {
        abort_unless(in_array(auth()->user()->role, ['super_admin', 'admin', 'koordinator']), 403);
        parent::mount();
    }
}
