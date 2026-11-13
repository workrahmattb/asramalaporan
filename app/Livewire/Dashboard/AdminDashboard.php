<?php

namespace App\Livewire\Dashboard;

use App\Models\DailyReport;
use App\Services\ReportStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class AdminDashboard extends Component
{
    use WithPagination;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function render(): View
    {
        $u = auth()->user();

        $daily = app(ReportStatisticsService::class)->daily($u, today()->toDateString());
        $total = $daily['missing']->count();
        $page = min($this->getPage('missingPage'), max(1, (int) ceil($total / 10)));
        $missingReports = new LengthAwarePaginator($daily['missing']->forPage($page, 10)->values(), $total, 10, $page, ['pageName' => 'missingPage']);

        return view('livewire.dashboard.index', ['daily' => $daily, 'missingReports' => $missingReports, 'summary' => app(ReportStatisticsService::class)->summary($u, now()->format('Y-m')), 'reports' => DailyReport::visibleTo($u)->with('items', 'asrama', 'user')->orderByDesc('report_date')->orderByDesc('id')->paginate(10, ['*'], 'recentPage'), 'todayReport' => $u->reports()->whereDate('report_date', today())->first()])->layout('layouts.simus');
    }
}
