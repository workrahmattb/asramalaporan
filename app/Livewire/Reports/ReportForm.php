<?php

namespace App\Livewire\Reports;

use App\Models\DailyReport;
use App\Models\DailyTask;
use App\Services\DailyReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ReportForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $reportId = null;

    public string $report_date = '';

    #[Locked]
    public bool $saved = false;

    /** @var list<int> */
    public array $completed = [];

    public string $obstacles = '';

    public string $notes = '';

    /** @var list<TemporaryUploadedFile> */
    public array $photos = [];

    public function mount(?DailyReport $report = null): void
    {
        $this->report_date = today()->toDateString();
        if ($report?->exists) {
            $this->authorize('update', $report);
            $this->reportId = $report->id;
            $this->report_date = $report->report_date->toDateString();
            $this->completed = [];
            foreach ($report->items()->where('is_completed', true)->get() as $item) {
                $this->completed[] = $item->daily_task_id;
            }
            $this->obstacles = $report->obstacles ?? '';
            $this->notes = $report->notes ?? '';
        } else {
            $this->authorize('create', DailyReport::class);
        }
    }

    public function removePhoto(int $index): void
    {
        $photos = $this->photos;
        unset($photos[$index]);
        $this->photos = array_values($photos);
    }

    public function save(string $status): void
    {
        $report = app(DailyReportService::class)->save(auth()->user(), ['report_date' => $this->report_date, 'completed' => $this->completed, 'obstacles' => $this->obstacles, 'notes' => $this->notes, 'status' => $status], $this->photos, $this->reportId ? DailyReport::findOrFail($this->reportId) : null);
        session()->flash('success', 'Laporan berhasil disimpan.');

        $this->saved = true;
        $this->dispatch('report-saved');
        $this->redirectRoute('reports.show', ['report' => $report->id], navigate: true);
    }

    public function render(): View
    {
        $report = $this->reportId ? DailyReport::with('items', 'photos')->findOrFail($this->reportId) : null;

        return view('livewire.reports.form', ['report' => $report, 'tasks' => $report ? $report->items->sortBy('sort_order') : DailyTask::where('is_active', true)->orderBy('sort_order')->get()])->layout('layouts.simus');
    }
}
