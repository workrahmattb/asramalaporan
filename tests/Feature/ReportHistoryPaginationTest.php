<?php

namespace Tests\Feature;

use App\Livewire\Reports\ReportHistory;
use App\Models\Asrama;
use App\Models\DailyReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ReportHistoryPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_shows_notes_and_ten_reports_per_page_for_admin_and_pembina(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));
        $user = User::factory()->create();
        $asrama = Asrama::create(['name' => 'Asrama tes', 'code' => 'TES']);
        for ($i = 0; $i < 11; $i++) {
            DailyReport::create(['user_id' => $user->id, 'asrama_id' => $asrama->id, 'report_date' => today()->subDays($i)->toDateString(), 'status' => 'draft', 'obstacles' => 'Kendala contoh '.$i, 'notes' => 'Catatan contoh '.$i]);
        }
        foreach ([$user, User::factory()->create(['role' => 'admin'])] as $viewer) {
            $this->actingAs($viewer);
            Livewire::test(ReportHistory::class)->assertViewHas('reports', fn ($reports) => $reports->perPage() === 10 && $reports->count() === 10 && $reports->total() === 11)
                ->assertSee('Kendala contoh 0')->assertSee('Catatan contoh 0')->assertDontSee('Kendala contoh 10')
                ->call('setPage', 2)->assertViewHas('reports', fn ($reports) => $reports->currentPage() === 2 && $reports->count() === 1)
                ->assertSee('Kendala contoh 10')->assertSee('Catatan contoh 10')
                ->set('status', 'submitted')->assertViewHas('reports', fn ($reports) => $reports->currentPage() === 1 && $reports->count() === 0);
        }
    }
}
