<?php

namespace App\Livewire\Admin;

use App\Models\Asrama;
use App\Models\Santri;
use App\Services\AsramaStudentService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class ManageAsramaStudents extends Component
{
    use WithPagination;

    #[Locked]
    public int $asramaId;

    public bool $showDropdown = false;

    public bool $confirmingRemoval = false;

    #[Locked]
    public ?int $removalId = null;

    public string $search = '';

    public string $candidateSearch = '';

    /** @var list<int> */
    public array $studentIds = [];

    public string $notice = '';

    public function mount(Asrama $asrama): void
    {
        $this->authorize('manage', Santri::class);
        $this->asramaId = $asrama->id;
    }

    /** @return Builder<Santri> */
    private function availableStudents(Asrama $asrama): Builder
    {
        return Santri::where('is_active', true)->whereDoesntHave('currentPlacement')
            ->when($asrama->gender, fn ($q) => $q->where(fn ($q) => $q->whereNull('gender')->orWhere('gender', $asrama->gender)));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCandidateSearch(): void
    {
        $this->showDropdown = true;
        $this->resetPage('candidatePage');
    }

    public function openCandidates(): void
    {
        $this->authorize('manage', Santri::class);
        $this->showDropdown = true;
    }

    public function selectStudent(int $id): void
    {
        $this->authorize('manage', Santri::class);
        if (in_array($id, $this->studentIds, true)) {
            $this->studentIds = array_values(array_diff($this->studentIds, [$id]));
        } else {
            $asrama = Asrama::findOrFail($this->asramaId);
            if (! $this->availableStudents($asrama)->whereKey($id)->exists()) {
                $this->addError('studentIds', 'Santri sudah ditempatkan, nonaktif, atau kategorinya tidak sesuai. Pilih santri lain.');

                return;
            }
            $this->studentIds[] = $id;
        }
        $this->showDropdown = true;
        $this->notice = '';
        $this->resetValidation();
    }

    public function clearSelection(): void
    {
        $this->reset('studentIds', 'candidateSearch', 'showDropdown');
        $this->resetPage('candidatePage');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorize('manage', Santri::class);
        $this->validate(['studentIds' => 'required|array|min:1', 'studentIds.*' => 'required|integer|distinct']);
        $count = app(AsramaStudentService::class)->assignMany(auth()->user(), $this->asramaId, $this->studentIds);
        $this->clearSelection();
        $this->resetPage();
        $this->notice = $count.' santri berhasil ditambahkan ke asrama.';
    }

    public function confirmRemove(int $id): void
    {
        $this->authorize('manage', Santri::class);
        if (! Santri::whereKey($id)->whereHas('currentPlacement', fn ($q) => $q->where('asrama_id', $this->asramaId))->exists()) {
            $this->addError('studentId', 'Penempatan santri sudah berubah. Muat ulang daftar asrama.');

            return;
        }
        $this->removalId = $id;
        $this->confirmingRemoval = true;
    }

    public function cancelRemove(): void
    {
        $this->reset('removalId', 'confirmingRemoval');
    }

    public function remove(): void
    {
        $this->authorize('manage', Santri::class);
        if (! $this->removalId) {
            $this->addError('studentId', 'Pilih santri yang akan dikeluarkan terlebih dahulu.');

            return;
        }
        app(AsramaStudentService::class)->remove(auth()->user(), $this->asramaId, $this->removalId);
        $this->cancelRemove();
        $this->resetPage();
        $this->notice = 'Santri dikeluarkan dari asrama. Identitas dan riwayatnya tetap tersimpan.';
    }

    public function render(): View
    {
        $this->authorize('manage', Santri::class);
        validator(['search' => $this->search, 'candidateSearch' => $this->candidateSearch, 'studentIds' => $this->studentIds], ['studentIds' => 'array', 'studentIds.*' => 'integer|distinct', 'search' => 'string|max:255', 'candidateSearch' => 'string|max:255'])->validate();
        $asrama = Asrama::findOrFail($this->asramaId);
        $students = Santri::whereHas('currentPlacement', fn ($q) => $q->where('asrama_id', $asrama->id))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')->orWhere('nisn', 'like', '%'.$this->search.'%')))
            ->orderBy('name')->orderBy('id')->paginate(10);
        $candidates = $this->availableStudents($asrama)
            ->when($this->candidateSearch, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->candidateSearch.'%')->orWhere('nisn', 'like', '%'.$this->candidateSearch.'%')))
            ->orderBy('name')->orderBy('id');

        return view('livewire.admin.manage-asrama-students', [
            'selectedStudents' => Santri::whereIn('id', $this->studentIds)->orderBy('name')->get(),
            'pendingRemoval' => $this->removalId ? Santri::find($this->removalId) : null,
            'asrama' => $asrama,
            'students' => $students,
            'candidates' => $this->showDropdown ? $candidates->paginate(10, pageName: 'candidatePage') : null,
        ])->layout('layouts.simus');
    }
}
