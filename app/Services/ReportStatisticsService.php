<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportStatisticsService
{
    /** @return array<string,mixed> */
    public function summary(User $user, string $month): array
    {
        $reports = DailyReport::visibleTo($user)->where('report_date', 'like', $month.'%')->where('status', '!=', 'draft')->with('items')->get();

        return ['reports' => $reports->count(), 'average' => round($reports->avg('percentage') ?? 0, 2), 'pending' => $reports->where('status', 'submitted')->count()];
    }

    /** @return list<array<string,mixed>> */
    public function monthly(User $viewer, string $month, ?int $asramaId = null, ?int $userId = null): array
    {
        $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth()->min(today());
        $assignments = DB::table('asrama_user')->join('users', 'users.id', '=', 'asrama_user.user_id')->join('asramas', 'asramas.id', '=', 'asrama_user.asrama_id')->whereIn('assignment_role', ['musyrif', 'musyrifah'])->whereDate('start_date', '<=', $end)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $start))->select('asrama_user.*', 'users.name as user_name', 'asramas.name as asrama_name');
        if ($viewer->isPembina()) {
            $assignments->where('user_id', $viewer->id);
        } elseif ($viewer->role === 'koordinator') {
            $assignments->whereIn('asrama_id', $viewer->assignedAsramas()->pluck('asramas.id'));
        }
        if ($asramaId) {
            $assignments->where('asrama_id', $asramaId);
        }
        if ($userId) {
            $assignments->where('user_id', $userId);
        }
        $reports = DailyReport::visibleTo($viewer)->whereDate('report_date', '>=', $start->toDateString())->whereDate('report_date', '<=', $end->toDateString())->with('items')->get()->groupBy(fn ($r) => $r->user_id.':'.$r->asrama_id);
        $rows = [];
        foreach ($assignments->get()->groupBy(fn ($a) => $a->user_id.':'.$a->asrama_id) as $key => $placements) {
            $dates = [];
            foreach ($placements as $a) {
                $from = $start->copy()->max(Carbon::parse($a->start_date));
                $until = $end->copy()->min($a->end_date ? Carbon::parse($a->end_date) : $end);
                for ($d = $from; $d->lte($until); $d->addDay()) {
                    $dates[$d->toDateString()] = true;
                }
            }
            $official = collect($reports->get($key, collect()))->where('status', '!=', 'draft');
            $submittedDates = $official->pluck('report_date')->map(fn ($d) => $d->toDateString())->all();
            $missing = array_values(array_diff(array_keys($dates), $submittedDates));
            $rows[] = ['user_id' => $placements->first()->user_id, 'asrama_id' => $placements->first()->asrama_id, 'name' => $placements->first()->user_name, 'asrama' => $placements->first()->asrama_name, 'expected' => count($dates), 'submitted' => $official->count(), 'average' => round($official->avg('percentage') ?? 0, 2), 'missing' => count($missing), 'missing_dates' => $missing];
        }

        return $rows;
    }

    /** @return array<string,mixed> */
    public function daily(User $viewer, string $date): array
    {
        $q = User::whereIn('role', ['musyrif', 'musyrifah'])->where('is_active', true)->whereHas('asramas', fn ($q) => $q->whereIn('assignment_role', ['musyrif', 'musyrifah'])->whereDate('start_date', '<=', $date)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date)));
        if ($viewer->isPembina()) {
            $q->whereKey($viewer->id);
        } elseif ($viewer->role === 'koordinator') {
            $q->whereHas('asramas', fn ($q) => $q->whereIn('asramas.id', $viewer->assignedAsramas()->pluck('asramas.id'))->whereIn('assignment_role', ['musyrif', 'musyrifah'])->whereDate('start_date', '<=', $date)->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $date)));
        }
        $users = $q->get();
        $reports = DailyReport::visibleTo($viewer)->whereDate('report_date', $date)->get();

        return ['musyrifah' => $users->count(), 'submitted' => $reports->where('status', '!=', 'draft')->count(), 'missing' => $users->filter(fn ($u) => ! $reports->where('user_id', $u->id)->where('status', '!=', 'draft')->count())->values(), 'pending' => $reports->where('status', 'submitted')->count()];
    }
}
