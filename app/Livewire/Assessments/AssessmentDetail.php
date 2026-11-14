<?php

namespace App\Livewire\Assessments;

use App\Models\SantriAssessment;
use App\Services\SantriAssessmentService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AssessmentDetail extends Component
{
    #[Locked]
    public int $assessmentId;

    public string $reviewerNotes = '';

    public function mount(SantriAssessment $assessment): void
    {
        $this->authorize('view', $assessment);
        $this->assessmentId = $assessment->id;
        $this->reviewerNotes = $assessment->reviewer_notes ?? '';
    }

    public function review(string $status): void
    {
        app(SantriAssessmentService::class)->review(auth()->user(), SantriAssessment::findOrFail($this->assessmentId), $status, $this->reviewerNotes);
        session()->flash('success', 'Hasil pemeriksaan disimpan.');
    }

    public function reopen(): void
    {
        app(SantriAssessmentService::class)->reopen(auth()->user(), SantriAssessment::findOrFail($this->assessmentId), $this->reviewerNotes);
        session()->flash('success', 'Penilaian dibuka untuk revisi.');
    }

    public function render(): View
    {
        $report = SantriAssessment::with('items', 'user', 'reviewer', 'period', 'santri')->findOrFail($this->assessmentId);
        $this->authorize('view', $report);

        return view('livewire.assessments.detail', ['report' => $report])->layout('layouts.simus');
    }
}
