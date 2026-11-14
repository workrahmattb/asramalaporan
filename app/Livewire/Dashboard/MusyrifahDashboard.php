<?php

namespace App\Livewire\Dashboard;

use App\Models\DailyReport;
use App\Models\Santri;
use App\Services\AcademicPeriodService;
use App\Services\ReportStatisticsService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class MusyrifahDashboard extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()->isPembina(), 403);
    }

    public function render(): View
    {
        $u = auth()->user();

        $period = app(AcademicPeriodService::class)->current();
        $students = Santri::visibleTo($u)->where('is_active', true);
        $studentCount = (clone $students)->count();
        $assessedCount = $period ? (clone $students)->whereHas('assessments', fn ($q) => $q->visibleTo($u)->where('assessment_period_id', $period->id)->whereIn('status', ['submitted', 'approved']))->count() : 0;

        return view('livewire.pembina.home', ['activePeriod' => $period, 'studentCount' => $studentCount, 'assessedCount' => $assessedCount, 'daily' => app(ReportStatisticsService::class)->daily($u, today()->toDateString()), 'summary' => app(ReportStatisticsService::class)->summary($u, now()->format('Y-m')), 'reports' => DailyReport::visibleTo($u)->with('items', 'asrama', 'user')->latest('report_date')->limit(6)->get(), 'todayReport' => $u->reports()->whereDate('report_date', today())->first()])->layout('layouts.simus');
    }
}
