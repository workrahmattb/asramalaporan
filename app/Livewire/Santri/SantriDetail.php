<?php

namespace App\Livewire\Santri;

use App\Models\AssessmentPeriod;
use App\Models\GuidanceNote;
use App\Models\Santri;
use App\Models\SantriAssessment;
use App\Services\AcademicPeriodService;
use App\Services\SantriService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class SantriDetail extends Component
{
    use WithPagination;

    #[Locked]
    public int $santriId;

    public string $periodId = '';

    public bool $showEditor = false;

    public string $note_date = '';

    public string $category = 'positive';

    public string $content = '';

    public string $follow_up = '';

    public function mount(Santri $santri): void
    {
        $this->authorize('view', $santri);
        $this->santriId = $santri->id;
        $this->periodId = (string) (app(AcademicPeriodService::class)->current()->id ?? '');
    }

    public function create(): void
    {
        $this->authorize('addNote', Santri::findOrFail($this->santriId));
        $this->cancel();
        $this->note_date = today()->toDateString();
        $this->showEditor = true;
    }

    public function cancel(): void
    {
        $this->reset('showEditor', 'note_date', 'category', 'content', 'follow_up');
        $this->resetValidation();
    }

    public function save(): void
    {
        app(SantriService::class)->addNote(auth()->user(), Santri::findOrFail($this->santriId), ['note_date' => $this->note_date, 'category' => $this->category, 'content' => $this->content, 'follow_up' => $this->follow_up]);
        $this->cancel();
        $this->resetPage();
        session()->flash('success', 'Catatan pembinaan ditambahkan.');
    }

    public function render(): View
    {
        $s = Santri::with('currentPlacement.asrama')->findOrFail($this->santriId);
        $this->authorize('view', $s);
        $this->validate(['periodId' => 'nullable|integer']);

        return view('livewire.santri.detail', ['santri' => $s, 'periods' => AssessmentPeriod::orderByDesc('start_date')->get(), 'selectedPeriod' => $this->periodId ? AssessmentPeriod::find($this->periodId) : null, 'assessment' => SantriAssessment::visibleTo(auth()->user())->where('santri_id', $s->id)->where('assessment_period_id', $this->periodId ?: 0)->first(), 'notesList' => GuidanceNote::visibleTo(auth()->user())->where('santri_id', $s->id)->with('user')->orderByDesc('note_date')->orderByDesc('id')->paginate(10), 'assessments' => SantriAssessment::visibleTo(auth()->user())->where('santri_id', $s->id)->latest()->paginate(10, pageName: 'assessmentPage')])->layout('layouts.simus');
    }
}
