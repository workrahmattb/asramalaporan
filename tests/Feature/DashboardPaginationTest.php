<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\AdminDashboard;
use App\Livewire\Dashboard\KoordinatorDashboard;
use App\Models\Asrama;
use App\Models\DailyReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_paginate_independently_for_admin_and_coordinator(): void
    {
        $asrama = Asrama::create(['name' => 'Tes', 'code' => 'TES']);
        for ($i = 0; $i < 21; $i++) {
            $user = User::factory()->create();
            $user->asramas()->attach($asrama, ['assignment_role' => 'musyrifah', 'start_date' => today()->toDateString()]);
            if ($i < 11) {
                DailyReport::create(['user_id' => $user->id, 'asrama_id' => $asrama->id, 'report_date' => today()->toDateString(), 'status' => 'draft']);
            }
        }
        foreach ([['admin', AdminDashboard::class], ['koordinator', KoordinatorDashboard::class]] as [$role,$componentClass]) {
            $viewer = User::factory()->create(['role' => $role]);
            if ($role === 'koordinator') {
                $viewer->asramas()->attach($asrama, ['assignment_role' => $role, 'start_date' => today()->toDateString()]);
            }
            $this->actingAs($viewer);
            Livewire::test($componentClass)
                ->assertViewHas('missingReports', fn ($p) => $p->count() === 10 && $p->total() === 21)
                ->assertViewHas('reports', fn ($p) => $p->count() === 10 && $p->total() === 11)
                ->call('setPage', 2, 'missingPage')
                ->assertViewHas('missingReports', fn ($p) => $p->currentPage() === 2 && $p->count() === 10)
                ->assertViewHas('reports', fn ($p) => $p->currentPage() === 1)
                ->call('setPage', 2, 'recentPage')
                ->assertViewHas('reports', fn ($p) => $p->currentPage() === 2 && $p->count() === 1)
                ->assertViewHas('missingReports', fn ($p) => $p->currentPage() === 2)
                ->call('setPage', 3, 'missingPage')
                ->assertViewHas('missingReports', fn ($p) => $p->count() === 1);
        }
    }
}
