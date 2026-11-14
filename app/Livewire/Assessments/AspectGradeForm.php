<?php

namespace App\Livewire\Assessments;

use App\Models\Asrama;
use App\Models\AssessmentAspect;
use App\Models\Santri;
use App\Services\AspectGradeService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AspectGradeForm extends Component
{
    #[Locked]
    public int $asramaId;

    #[Locked]
    public int $aspectId;

    #[Locked]
    public int $page = 1;

    /** @var array<int,string> */
    public array $ratings = [];

    /** @var array<int,int|null> */
    #[Locked]
    public array $versions = [];

    public function mount(Asrama $asrama, AssessmentAspect $aspect): void
    {
        $this->asramaId = $asrama->id;
        $this->aspectId = $aspect->id;
        $this->loadGrades();
    }

    /** @return Builder<Santri> */
    private function students(): Builder
    {
        $aspect = AssessmentAspect::findOrFail($this->aspectId);

        return Santri::visibleTo(auth()->user())->where('is_active', true)
            ->whereHas('currentPlacement', fn ($q) => $q->where('asrama_id', $this->asramaId))
            ->with(['assessments' => fn ($q) => $q->visibleTo(auth()->user())->where('assessment_period_id', $aspect->assessment_period_id)->with('items')])
            ->orderBy('name')->orderBy('id');
    }

    private function loadGrades(): void
    {
        $aspect = AssessmentAspect::findOrFail($this->aspectId);
        app(AspectGradeService::class)->authorizeContext(auth()->user(), Asrama::findOrFail($this->asramaId), $aspect);
        $this->ratings = [];
        $this->versions = [];
        foreach ($this->students()->paginate(10, pageName: 'gradePage', page: $this->page) as $student) {
            $report = $student->assessments->first();
            if (! $report || auth()->user()->can('update', $report)) {
                $this->ratings[$student->id] = $report?->items->firstWhere('assessment_aspect_id', $aspect->id)->rating ?? '';
                $this->versions[$student->id] = $report?->version;
            }
        }
        $this->resetValidation();
        $this->dispatch('grades-loaded');
    }

    public function goToPage(int $page): void
    {
        abort_unless($page >= 1 && $page <= max(1, (int) ceil($this->students()->count() / 10)), 422);
        $this->page = $page;
        $this->loadGrades();
    }

    public function save(): void
    {
        $this->validate(['ratings' => 'array', 'ratings.*' => 'nullable|string|max:100']);
        if (array_diff(array_keys($this->ratings), array_keys($this->versions)) || array_diff(array_keys($this->versions), array_keys($this->ratings))) {
            $this->addError('ratings', 'Daftar santri berubah. Muat ulang halaman.');

            return;
        }
        $rows = [];
        foreach ($this->versions as $id => $version) {
            $rows[$id] = ['rating' => $this->ratings[$id] ?? '', 'version' => $version];
        }
        if ($rows === []) {
            return;
        }
        app(AspectGradeService::class)->save(auth()->user(), Asrama::findOrFail($this->asramaId), AssessmentAspect::findOrFail($this->aspectId), $rows);
        $this->loadGrades();
        $this->dispatch('grades-saved');
    }

    public function render(): View
    {
        $asrama = Asrama::findOrFail($this->asramaId);
        $aspect = AssessmentAspect::with('period')->findOrFail($this->aspectId);
        app(AspectGradeService::class)->authorizeContext(auth()->user(), $asrama, $aspect);

        return view('livewire.assessments.aspect-grade-form', ['asrama' => $asrama, 'aspect' => $aspect, 'students' => $this->students()->paginate(10, pageName: 'gradePage', page: $this->page)])->layout('layouts.simus');
    }
}
