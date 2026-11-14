<?php

namespace App\Livewire\Admin;

use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Santri;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ManageAssessmentPeriods extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editId = null;

    #[Locked]
    public bool $centralized = false;

    #[Locked]
    public bool $rubricOnly = false;

    public bool $showEditor = false;

    public string $name = '';

    public string $start_date = '';

    public string $end_date = '';

    public bool $is_active = false;

    public string $academicYearId = '';

    public string $semester = 'Ganjil';

    /** @var list<int|string> */
    public array $subjectIds = [];

    public string $aspectTitles = "Ibadah\nAdab\nKebersihan\nKedisiplinan";

    public string $ratingLabels = "A\nB\nC\nD";

    public function mount(): void
    {
        $this->authorize('manage', Santri::class);
    }

    #[On('configure-semester')]
    public function configureSemester(int $id): void
    {
        $this->edit($id);
    }

    #[On('academic-context-updated')]
    #[On('rapor-masters-updated')]
    public function refreshMasters(): void
    {
        $this->resetPage('periodPage');
    }

    public function create(): void
    {
        $this->authorize('manage', Santri::class);
        $this->cancel();
        $this->start_date = today()->startOfMonth()->toDateString();
        $this->end_date = today()->endOfMonth()->toDateString();
        $this->academicYearId = (string) (AcademicYear::where('is_active', true)->value('id') ?? '');
        $this->subjectIds = array_values(AssessmentSubject::where('is_active', true)->get()->map(fn ($subject) => $subject->id)->all());
        $this->showEditor = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('manage', Santri::class);
        $this->cancel();
        $p = AssessmentPeriod::findOrFail($id);
        $this->editId = $id;
        $this->name = $p->name;
        $this->academicYearId = (string) ($p->academic_year_id ?? '');
        $this->semester = $p->semester ?? '';
        $this->subjectIds = array_values($p->aspects->whereNotNull('assessment_subject_id')->map(fn ($aspect) => $aspect->assessment_subject_id)->all());
        $this->start_date = $p->start_date->toDateString();
        $this->end_date = $p->end_date->toDateString();
        $this->is_active = $p->is_active;
        $this->aspectTitles = $p->aspects->pluck('title')->implode("\n");
        $this->ratingLabels = implode("\n", $p->rating_labels);
        $this->showEditor = true;
    }

    public function cancel(): void
    {
        $this->resetExcept('paginators', 'centralized', 'rubricOnly');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('manage', Santri::class);
        if ($this->centralized) {
            $current = $this->editId ? AssessmentPeriod::findOrFail($this->editId) : null;
            $this->is_active = $current->is_active ?? false;
            if ($current && $current->is_active) {
                $this->academicYearId = (string) ($current->academic_year_id ?? '');
                $this->semester = $current->semester ?? '';
            }
        }
        if ($this->rubricOnly && $this->editId) {
            $current = AssessmentPeriod::findOrFail($this->editId);
            $this->academicYearId = (string) ($current->academic_year_id ?? '');
            $this->semester = $current->semester ?? '';
            $this->name = $current->name;
            $this->start_date = $current->start_date->toDateString();
            $this->end_date = $current->end_date->toDateString();
        }
        $valid = $this->validate(['name' => 'required|string|max:255', 'academicYearId' => ($this->editId ? 'nullable' : 'required').'|integer|exists:academic_years,id', 'semester' => ($this->editId ? 'nullable' : 'required').'|in:Ganjil,Genap', 'subjectIds' => 'array', 'subjectIds.*' => 'integer|distinct|exists:assessment_subjects,id', 'start_date' => 'required|date_format:Y-m-d', 'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date', 'is_active' => 'boolean', 'aspectTitles' => 'string|max:10000', 'ratingLabels' => 'required|string|max:3000']);
        $subjects = AssessmentSubject::whereIn('id', $this->subjectIds)->orderBy('title')->get();
        if (! $this->editId && ($subjects->isEmpty() || $subjects->contains('is_active', false))) {
            throw ValidationException::withMessages(['subjectIds' => 'Pilih minimal satu aspek aktif dari master.']);
        }
        $aspects = $subjects->isNotEmpty() ? $subjects->pluck('title')->all() : array_values(array_filter(array_map('trim', explode("\n", $this->aspectTitles)), fn ($s) => $s !== ''));
        $labels = array_values(array_filter(array_map('trim', explode("\n", $this->ratingLabels)), fn ($s) => $s !== ''));
        validator(['aspects' => $aspects, 'labels' => $labels], ['aspects' => 'required|array|min:1|max:30', 'aspects.*' => 'required|string|max:255|distinct', 'labels' => 'required|array|min:2|max:10', 'labels.*' => 'required|string|max:100|distinct'])->validate();
        DB::transaction(function () use ($valid, $aspects, $labels, $subjects) {
            AssessmentPeriod::orderBy('id')->lockForUpdate()->get();
            $period = $this->editId ? AssessmentPeriod::whereKey($this->editId)->lockForUpdate()->firstOrFail() : new AssessmentPeriod;
            if ($this->academicYearId && $this->is_active && ! AcademicYear::whereKey($this->academicYearId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['academicYearId' => 'Aktifkan tahun ajaran ini terlebih dahulu.']);
            }
            if ($this->academicYearId && AssessmentPeriod::where('academic_year_id', $this->academicYearId)->where('semester', $this->semester)->when($this->editId, fn ($q) => $q->where('id', '!=', $this->editId))->exists()) {
                throw ValidationException::withMessages(['semester' => 'Semester ini sudah tersedia pada tahun ajaran tersebut.']);
            }
            $locked = $period->exists && $period->assessments()->exists();
            if ($locked && (($subjects->isNotEmpty() ? $period->aspects->pluck('assessment_subject_id')->sort()->values()->all() !== $subjects->pluck('id')->sort()->values()->all() : $period->aspects->pluck('title')->all() !== $aspects) || $period->rating_labels !== $labels || $period->academic_year_id !== ($this->academicYearId ? (int) $this->academicYearId : null) || $period->semester !== ($this->semester ?: null) || $this->aspectTitles !== $period->aspects->pluck('title')->implode("\n"))) {
                throw ValidationException::withMessages(['aspectTitles' => 'Aspek dan skala telah digunakan. Buat periode baru untuk mengubahnya.']);
            }
            $period->fill(['academic_year_id' => $this->academicYearId ?: null, 'semester' => $this->semester ?: null, 'name' => $valid['name'], 'start_date' => $valid['start_date'], 'end_date' => $valid['end_date'], 'is_active' => $valid['is_active'], 'rating_labels' => $labels]);
            $period->save();
            if ($period->is_active) {
                AssessmentPeriod::where('id', '!=', $period->id)->update(['is_active' => false]);
            }
            if (! $locked) {
                $period->aspects()->delete();
                foreach ($aspects as $i => $title) {
                    $subject = $subjects->firstWhere('title', $title);
                    $period->aspects()->create(['assessment_subject_id' => $subject?->id, 'title' => $title, 'indicator' => $subject?->indicator, 'sort_order' => $i + 1]);
                }
            }
        }, 3);
        $this->cancel();
        $this->dispatch('assessment-periods-updated');
        session()->flash('success', 'Periode penilaian disimpan.');
    }

    public function render(): View
    {
        $this->authorize('manage', Santri::class);

        return view('livewire.admin.assessment-periods', ['years' => AcademicYear::orderByDesc('name')->get(), 'subjects' => AssessmentSubject::orderBy('title')->get(), 'periods' => AssessmentPeriod::withCount('assessments')->orderByDesc('start_date')->paginate(10, pageName: 'periodPage'), 'rubricLocked' => $this->editId ? AssessmentPeriod::findOrFail($this->editId)->assessments()->exists() : false])->layout('layouts.simus');
    }
}
