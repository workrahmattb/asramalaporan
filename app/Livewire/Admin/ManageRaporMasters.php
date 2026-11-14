<?php

namespace App\Livewire\Admin;

use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Santri;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ManageRaporMasters extends Component
{
    use WithPagination;

    #[Locked]
    public string $kind = 'subject';

    #[Locked]
    public ?int $editId = null;

    #[Locked]
    public bool $centralized = false;

    public bool $showEditor = false;

    public string $title = '';

    public string $indicator = '';

    public bool $is_active = true;

    #[On('academic-context-updated')]
    public function refreshContext(): void {}

    public function create(string $kind = 'subject'): void
    {
        $this->authorize('manage', Santri::class);
        abort_unless(in_array($kind, ['subject', 'year'], true), 422);
        $this->cancel();
        $this->kind = $kind;
        if ($this->centralized && $kind === 'year') {
            $this->is_active = false;
        }
        $this->showEditor = true;
    }

    public function edit(string $kind, int $id): void
    {
        $this->create($kind);
        $model = $kind === 'year' ? AcademicYear::findOrFail($id) : AssessmentSubject::findOrFail($id);
        $this->editId = $id;
        $this->title = $kind === 'year' ? $model->getAttribute('name') : $model->getAttribute('title');
        $this->indicator = $model->getAttribute('indicator') ?? '';
        $this->is_active = $model->is_active;
    }

    public function cancel(): void
    {
        $this->reset('showEditor', 'editId', 'title', 'indicator', 'is_active');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('manage', Santri::class);
        if ($this->centralized && $this->kind === 'year') {
            $this->is_active = $this->editId ? AcademicYear::findOrFail($this->editId)->is_active : false;
        }
        $table = $this->kind === 'year' ? 'academic_years,name' : 'assessment_subjects,title';
        $this->validate(['title' => 'required|string|max:255|unique:'.$table.','.($this->editId ?? 'NULL'), 'indicator' => 'nullable|string|max:10000', 'is_active' => 'boolean']);
        DB::transaction(function () {
            if ($this->kind === 'year') {
                AcademicYear::orderBy('id')->lockForUpdate()->get();
                $year = $this->editId ? AcademicYear::findOrFail($this->editId) : new AcademicYear;
                $year->fill(['name' => $this->title, 'is_active' => $this->is_active])->save();
                if (! $this->centralized && $year->is_active) {
                    AcademicYear::where('id', '!=', $year->id)->update(['is_active' => false]);
                    AssessmentPeriod::where('academic_year_id', '!=', $year->id)->orWhereNull('academic_year_id')->update(['is_active' => false]);
                } elseif (! $this->centralized) {
                    AssessmentPeriod::where('academic_year_id', $year->id)->update(['is_active' => false]);
                }
            } else {
                $subject = $this->editId ? AssessmentSubject::findOrFail($this->editId) : new AssessmentSubject;
                $subject->fill(['title' => $this->title, 'indicator' => $this->indicator ?: null, 'is_active' => $this->is_active])->save();
            }
        }, 3);
        $this->cancel();
        $this->dispatch('rapor-masters-updated');
    }

    public function render(): View
    {
        $this->authorize('manage', Santri::class);

        return view('livewire.admin.manage-rapor-masters', [
            'years' => AcademicYear::orderByDesc('name')->get(),
            'subjects' => AssessmentSubject::orderBy('title')->paginate(10, pageName: 'subjectPage'),
        ])->layout('layouts.simus');
    }
}
