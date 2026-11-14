<?php

namespace App\Livewire\Assessments;

use App\Models\Asrama;
use App\Models\Santri;
use App\Services\AcademicPeriodService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RaporAsrama extends Component
{
    use WithPagination;

    #[Url]
    public string $asramaId = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $progress = '';

    public string $aspectId = '';

    public function mount(): void
    {
        $asramas = auth()->user()->assignedAsramas()->pluck('asramas.id');
        if (! $this->asramaId && $asramas->count() === 1) {
            $this->asramaId = (string) $asramas->first();
        }
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = auth()->user();
        abort_unless($user->isPembina() || $user->isAdmin() || $user->role === 'koordinator', 403);
        $this->validate(['aspectId' => 'nullable|integer', 'asramaId' => 'nullable|integer', 'search' => 'string|max:255', 'progress' => 'in:,incomplete']);
        $period = app(AcademicPeriodService::class)->current()?->load('aspects');
        $query = Santri::visibleTo($user)->where('is_active', true)->with('currentPlacement.asrama')
            ->with(['assessments' => fn ($q) => $q->visibleTo($user)->where('assessment_period_id', $period->id ?? 0)->with('items')])
            ->when($this->asramaId, fn ($q) => $q->whereHas('currentPlacement', fn ($q) => $q->where('asrama_id', $this->asramaId)))
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->search.'%')->orWhere('nisn', 'like', '%'.$this->search.'%')));
        if ($period && $this->progress === 'incomplete') {
            $query->whereDoesntHave('assessments', fn ($q) => $q->visibleTo($user)->where('assessment_period_id', $period->id)->whereIn('status', ['submitted', 'approved']));
        }

        return view('livewire.assessments.rapor-asrama', [
            'selectedAspect' => $period ? $period->aspects->firstWhere('id', $this->aspectId) : null,
            'selectedAsrama' => $this->asramaId ? ($user->isAdmin() ? Asrama::where('is_active', true) : $user->assignedAsramas())->where('asramas.id', $this->asramaId)->first() : null,
            'period' => $period,
            'students' => $query->orderBy('name')->orderBy('id')->paginate(10),
            'asramas' => $user->isAdmin() ? Asrama::where('is_active', true)->get() : $user->assignedAsramas()->get(),
        ])->layout('layouts.simus');
    }
}
