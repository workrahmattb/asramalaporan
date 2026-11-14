<?php

namespace App\Livewire;

use App\Models\AcademicYear;
use App\Services\AcademicPeriodService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class ActiveAcademicPeriod extends Component
{
    #[On('rapor-masters-updated')]
    #[On('assessment-periods-updated')]
    #[On('academic-context-updated')]
    public function refreshPeriod(): void {}

    public function render(): View
    {
        return view('livewire.active-academic-period', ['activeYear' => AcademicYear::where('is_active', true)->first(), 'period' => app(AcademicPeriodService::class)->current()]);
    }
}
