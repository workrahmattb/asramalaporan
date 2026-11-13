<?php

namespace App\Livewire\Reports;

use App\Models\Asrama;
use App\Models\DailyReport;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class ReportHistory extends Component
{
    use WithPagination;

    public string $month = '';

    public string $date = '';

    public string $status = '';

    public string $asramaId = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $asramas = auth()->user()->isAdmin() ? Asrama::all() : auth()->user()->assignedAsramas()->get();
        $q = DailyReport::visibleTo(auth()->user())->with('user', 'asrama', 'items')->when($this->month, fn ($q) => $q->where('report_date', 'like', $this->month.'%'))->when($this->date, fn ($q) => $q->whereDate('report_date', $this->date))->when($this->asramaId, fn ($q) => $q->where('asrama_id', $this->asramaId))->when($this->status, fn ($q) => $q->where('status', $this->status));

        return view('livewire.reports.history', ['asramas' => $asramas, 'reports' => $q->orderByDesc('report_date')->paginate(15)])->layout('layouts.simus');
    }
}
