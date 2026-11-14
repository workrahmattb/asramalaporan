<?php

namespace App\Livewire\Santri;

use App\Models\Asrama;
use App\Models\AssessmentPeriod;
use App\Models\Santri;
use App\Services\AcademicPeriodService;
use App\Services\SantriService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SantriIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $asramaId = '';

    #[Url]
    public string $periodId = '';

    #[Url]
    public string $progress = '';

    public string $active = '1';

    #[Locked]
    public ?int $editId = null;

    public bool $showEditor = false;

    public string $name = '';

    public string $nisn = '';

    public string $gender = '';

    public string $birth_place = '';

    public string $birth_date = '';

    public string $father_name = '';

    public string $mother_name = '';

    public string $father_phone = '';

    public string $mother_phone = '';

    public string $asrama_id = '';

    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isPembina() || auth()->user()->role === 'koordinator', 403);
        if (! $this->periodId) {
            $this->periodId = (string) (app(AcademicPeriodService::class)->current()->id ?? '');
        }
    }

    public function updated(string $field): void
    {
        if (in_array($field, ['search', 'asramaId', 'periodId', 'progress', 'active'])) {
            $this->resetPage();
        }
    }

    public function create(): void
    {
        $this->authorize('manage', Santri::class);
        $this->cancel();
        $this->showEditor = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('manage', Santri::class);
        $this->cancel();
        $s = Santri::findOrFail($id);
        $this->editId = $id;
        $this->name = $s->name;
        $this->nisn = $s->nisn ?? '';
        $this->gender = $s->gender ?? '';
        foreach (['birth_place', 'father_name', 'mother_name', 'father_phone', 'mother_phone'] as $field) {
            $this->$field = $s->$field ?? '';
        }
        $this->birth_date = $s->birth_date?->toDateString() ?? '';
        $this->is_active = $s->is_active;
        $this->asrama_id = (string) ($s->currentPlacement->asrama_id ?? '');
        $this->showEditor = true;
    }

    public function cancel(): void
    {
        $this->reset('editId', 'showEditor', 'name', 'nisn', 'gender', 'asrama_id', 'is_active', 'birth_place', 'birth_date', 'father_name', 'mother_name', 'father_phone', 'mother_phone');
        $this->resetValidation();
    }

    public function save(): void
    {
        app(SantriService::class)->save(auth()->user(), ['birth_place' => $this->birth_place, 'birth_date' => $this->birth_date, 'father_name' => $this->father_name, 'mother_name' => $this->mother_name, 'father_phone' => $this->father_phone, 'mother_phone' => $this->mother_phone, 'name' => $this->name, 'nisn' => $this->nisn, 'gender' => $this->gender, 'asrama_id' => $this->asrama_id, 'is_active' => $this->is_active], $this->editId);
        $this->cancel();
        session()->flash('success', 'Data santri dan penempatan disimpan.');
    }

    public function render(): View
    {
        $u = auth()->user();
        $this->validate(['periodId' => 'nullable|integer', 'asramaId' => 'nullable|integer', 'progress' => 'in:,incomplete', 'active' => 'in:,0,1', 'search' => 'string|max:255']);
        $q = Santri::visibleTo($u)->with('currentPlacement.asrama')->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')->orWhere('nisn', 'like', '%'.$this->search.'%')))->when($this->asramaId, fn ($q) => $q->whereHas('currentPlacement', fn ($q) => $q->where('asrama_id', $this->asramaId)));
        if ($u->isAdmin()) {
            if ($this->active !== '') {
                $q->where('is_active', $this->active === '1');
            }
        } else {
            $q->where('is_active', true);
        }
        if (! $u->isPembina() && $this->progress === 'incomplete' && $this->periodId) {
            $q->whereDoesntHave('assessments', fn ($q) => $q->visibleTo($u)->where('assessment_period_id', $this->periodId)->whereIn('status', ['submitted', 'approved']));
        }

        return view('livewire.santri.index', ['santris' => $q->orderBy('name')->orderBy('id')->paginate(10), 'asramas' => $u->isAdmin() ? Asrama::where('is_active', true)->get() : $u->assignedAsramas()->get(), 'periods' => AssessmentPeriod::orderByDesc('start_date')->get()])->layout('layouts.simus');
    }
}
