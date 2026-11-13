<?php

namespace App\Livewire\Reports;

use App\Models\DailyReport;
use App\Services\DailyReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReportDetail extends Component
{
    #[Locked]
    public int $reportId;

    public string $reviewerNotes = '';

    public function mount(DailyReport $report): void
    {
        $this->authorize('view', $report);
        $this->reportId = $report->id;
        $this->reviewerNotes = $report->reviewer_notes ?? '';
    }

    public function review(string $status): void
    {
        app(DailyReportService::class)->review(auth()->user(), DailyReport::findOrFail($this->reportId), $status, $this->reviewerNotes);
        session()->flash('success', 'Status laporan diperbarui.');
    }

    public function render(): View
    {
        $r = DailyReport::with('user', 'asrama', 'items', 'photos')->findOrFail($this->reportId);
        $this->authorize('view', $r);

        return view('livewire.reports.detail', ['report' => $r])->layout('layouts.simus');
    }
}
