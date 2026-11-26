<?php

namespace App\Livewire\Reports;

use App\Models\DailyReport;
use Illuminate\Database\Eloquent\Builder;

class ReportMonitoring extends ReportHistory
{
    public string $pembinaRole = '';

    public function updatedPembinaRole(): void
    {
        $this->resetPage();
        $this->validateOnly('pembinaRole', ['pembinaRole' => 'in:,musyrif,musyrifah']);
    }

    /** @param Builder<DailyReport> $query
     * @return Builder<DailyReport> */
    protected function filterReports(Builder $query): Builder
    {
        return $query->when($this->pembinaRole, fn ($q) => $q->whereHas('user', fn ($q) => $q->where('role', $this->pembinaRole)));
    }

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()->role, ['super_admin', 'admin', 'koordinator']), 403);
        parent::mount();
    }
}
