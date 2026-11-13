<?php

namespace App\Livewire\Reports;

use App\Models\Asrama;
use App\Models\User;
use App\Services\ReportStatisticsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MonthlyRecap extends Component
{
    public string $month = '';

    public string $asramaId = '';

    public string $userId = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function render(): View
    {
        $this->validate(['month' => 'required|date_format:Y-m', 'asramaId' => 'nullable|integer', 'userId' => 'nullable|integer']);
        $u = auth()->user();
        $asramas = $u->isAdmin() ? Asrama::all() : $u->assignedAsramas()->get();
        $users = User::whereIn('role', ['musyrif', 'musyrifah'])->when($u->isPembina(), fn ($q) => $q->whereKey($u->id))->when($u->role === 'koordinator', fn ($q) => $q->whereHas('asramas', fn ($q) => $q->whereIn('asramas.id', $asramas->pluck('id'))))->get();

        return view('livewire.reports.monthly', ['rows' => app(ReportStatisticsService::class)->monthly($u, $this->month, $this->asramaId ? (int) $this->asramaId : null, $this->userId ? (int) $this->userId : null), 'asramas' => $asramas, 'users' => $users])->layout('layouts.simus');
    }
}
