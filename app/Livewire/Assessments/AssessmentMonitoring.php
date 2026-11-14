<?php

namespace App\Livewire\Assessments;

use App\Models\Asrama;
use App\Models\AssessmentPeriod;
use App\Models\SantriAssessment;
use App\Services\AcademicPeriodService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class AssessmentMonitoring extends Component
{
    use WithPagination;

    public string $periodId = '';

    public bool $showHistory = false;

    public string $status = 'submitted';

    public string $asramaId = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->role === 'koordinator', 403);
    }

    #[On('assessment-periods-updated')]
    public function refreshPeriods(): void
    {
        $this->resetPage();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->role === 'koordinator', 403);
        $this->validate(['periodId' => 'nullable|integer', 'asramaId' => 'nullable|integer', 'status' => 'in:,draft,submitted,revision,approved']);
        $currentPeriod = app(AcademicPeriodService::class)->current();
        $selectedPeriodId = $this->showHistory ? $this->periodId : ($currentPeriod->id ?? 0);
        $q = SantriAssessment::visibleTo(auth()->user())->when(! $this->showHistory || $selectedPeriodId !== '', fn ($q) => $q->where('assessment_period_id', $selectedPeriodId))->when($this->status, fn ($q) => $q->where('status', $this->status))->when($this->asramaId, fn ($q) => $q->where('asrama_id', $this->asramaId));

        return view('livewire.assessments.monitoring', ['currentPeriod' => $currentPeriod, 'reports' => $q->orderByDesc('updated_at')->orderByDesc('id')->paginate(10), 'periods' => AssessmentPeriod::orderByDesc('start_date')->get(), 'asramas' => auth()->user()->isAdmin() ? Asrama::all() : auth()->user()->assignedAsramas()->get()])->layout('layouts.simus');
    }
}
