<?php

namespace App\Livewire\Admin;

use App\Models\AcademicYear;
use App\Models\Santri;
use App\Services\AcademicYearService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class AcademicSettings extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editId = null;

    public bool $showEditor = false;

    public string $name = '';

    public string $start_date = '';

    public string $end_date = '';

    public string $notice = '';

    public function create(): void
    {
        $this->authorize('manage', Santri::class);
        $this->cancel();
        $this->showEditor = true;
    }

    public function edit(int $id): void
    {
        $this->create();
        $year = AcademicYear::findOrFail($id);
        $this->editId = $year->id;
        $this->name = $year->name;
        $this->start_date = $year->start_date?->toDateString() ?? '';
        $this->end_date = $year->end_date?->toDateString() ?? '';
    }

    public function cancel(): void
    {
        $this->reset('editId', 'showEditor', 'name', 'start_date', 'end_date');
        $this->resetValidation();
    }

    public function save(): void
    {
        app(AcademicYearService::class)->save(auth()->user(), ['name' => $this->name, 'start_date' => $this->start_date, 'end_date' => $this->end_date], $this->editId);
        $this->cancel();
        $this->notice = 'Tahun ajaran disimpan. Semester Ganjil dan Genap tersedia di bawahnya.';
        $this->changed();
    }

    public function toggleYear(int $id): void
    {
        app(AcademicYearService::class)->toggleYear(auth()->user(), $id);
        $this->notice = 'Status tahun ajaran diperbarui untuk seluruh aplikasi.';
        $this->changed();
    }

    public function toggleSemester(int $id): void
    {
        app(AcademicYearService::class)->toggleSemester(auth()->user(), $id);
        $this->notice = 'Status semester diperbarui untuk seluruh aplikasi.';
        $this->changed();
    }

    public function delete(int $id): void
    {
        app(AcademicYearService::class)->delete(auth()->user(), $id);
        $this->notice = 'Tahun ajaran kosong beserta semesternya dihapus.';
        $this->resetPage();
        $this->changed();
    }

    public function configureSemester(int $id): void
    {
        $this->authorize('manage', Santri::class);
        $this->dispatch('configure-semester', id: $id);
    }

    private function changed(): void
    {
        $this->dispatch('academic-context-updated');
        $this->dispatch('rapor-masters-updated');
    }

    public function render(): View
    {
        $this->authorize('manage', Santri::class);

        return view('livewire.admin.academic-settings', ['years' => AcademicYear::with(['semesters' => fn ($q) => $q->withCount('assessments')])->orderByDesc('name')->orderByDesc('id')->paginate(10)])->layout('layouts.simus');
    }
}
