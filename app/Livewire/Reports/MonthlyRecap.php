<?php

namespace App\Livewire\Reports;

use App\Models\Asrama;
use App\Models\User;
use App\Services\ReportStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Livewire\Component;
use Livewire\WithPagination;

class MonthlyRecap extends Component
{
    use WithPagination;

    public string $month = '';

    public string $asramaId = '';

    public string $userId = '';

    public string $pembinaRole = '';

    public function updatedPembinaRole(): void
    {
        $this->userId = '';
        $this->resetPage();
    }

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function updated(string $field): void
    {
        if (in_array($field, ['month', 'asramaId', 'userId', 'pembinaRole'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->validate(['pembinaRole' => 'in:,musyrif,musyrifah', 'month' => 'required|date_format:Y-m', 'asramaId' => 'nullable|integer', 'userId' => 'nullable|integer']);
        $u = auth()->user();
        $asramas = $u->isAdmin() ? Asrama::all() : $u->assignedAsramas()->get();
        $users = User::whereIn('role', ['musyrif', 'musyrifah'])->when($this->pembinaRole, fn ($q) => $q->where('role', $this->pembinaRole))->when($u->isPembina(), fn ($q) => $q->whereKey($u->id))->when($u->role === 'koordinator', fn ($q) => $q->whereHas('asramas', fn ($q) => $q->whereIn('asramas.id', $asramas->pluck('id'))))->get();

        $data = app(ReportStatisticsService::class)->monthly($u, $this->month, $this->asramaId ? (int) $this->asramaId : null, $this->userId ? (int) $this->userId : null, $this->pembinaRole ?: null);
        usort($data, static fn (array $a, array $b): int => [$a['name'], $a['asrama'], $a['user_id'], $a['asrama_id']] <=> [$b['name'], $b['asrama'], $b['user_id'], $b['asrama_id']]);
        $page = min($this->getPage(), max(1, (int) ceil(count($data) / 10)));
        if ($page !== $this->getPage()) {
            $this->setPage($page);
        }
        $rows = new LengthAwarePaginator(array_slice($data, ($page - 1) * 10, 10), count($data), 10, $page, ['path' => Paginator::resolveCurrentPath()]);

        return view('livewire.reports.monthly', ['rows' => $rows, 'asramas' => $asramas, 'users' => $users])->layout('layouts.simus');
    }
}
