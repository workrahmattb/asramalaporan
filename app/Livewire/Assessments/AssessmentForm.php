<?php

namespace App\Livewire\Assessments;

use App\Models\AssessmentPeriod;
use App\Models\Santri;
use App\Models\SantriAssessment;
use App\Services\SantriAssessmentService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AssessmentForm extends Component
{
    #[Locked]
    public int $santriId;

    #[Locked]
    public int $periodId;

    #[Locked]
    public ?int $version = null;

    #[Locked]
    public bool $saved = false;

    /** @var array<int,string> */
    public array $ratings = [];

    /** @var array<int,string> */
    public array $itemNotes = [];

    public string $development = '';

    public string $nextFocus = '';

    public function mount(Santri $santri, AssessmentPeriod $period): void
    {
        $this->authorize('view', $santri);
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isPembina(), 403);
        $this->santriId = $santri->id;
        $this->periodId = $period->id;
        $report = SantriAssessment::where('santri_id', $santri->id)->where('assessment_period_id', $period->id)->first();
        if ($report) {
            $this->authorize('update', $report);
            $this->version = $report->version;
            $this->development = $report->development ?? '';
            $this->nextFocus = $report->next_focus ?? '';
            foreach ($report->items as $item) {
                $this->ratings[$item->assessment_aspect_id] = $item->rating ?? '';
                $this->itemNotes[$item->assessment_aspect_id] = $item->notes ?? '';
            }
        } else {
            abort_unless($period->isOpen() && $santri->is_active && $santri->currentPlacement, 403);
            foreach ($period->aspects as $aspect) {
                $this->ratings[$aspect->id] = '';
                $this->itemNotes[$aspect->id] = '';
            }
        }
    }

    public function save(string $status): void
    {
        $report = app(SantriAssessmentService::class)->save(auth()->user(), Santri::findOrFail($this->santriId), AssessmentPeriod::findOrFail($this->periodId), ['status' => $status, 'ratings' => $this->ratings, 'item_notes' => $this->itemNotes, 'development' => $this->development, 'next_focus' => $this->nextFocus], $this->version);
        $this->saved = true;
        $this->dispatch('assessment-saved');
        session()->flash('success', $status === 'submitted' ? 'Penilaian dikirim untuk diperiksa.' : 'Draft penilaian disimpan.');
        $this->redirectRoute('assessments.show', ['assessment' => $report->id], navigate: true);
    }

    public function render(): View
    {
        $santri = Santri::findOrFail($this->santriId);
        $this->authorize('view', $santri);
        $period = AssessmentPeriod::with('aspects')->findOrFail($this->periodId);
        $r = SantriAssessment::with('items')->where('santri_id', $this->santriId)->where('assessment_period_id', $this->periodId)->first();

        return view('livewire.assessments.form', ['santri' => $santri, 'period' => $period, 'report' => $r])->layout('layouts.simus');
    }
}
