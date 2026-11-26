<?php

namespace Tests\Feature;

use App\Livewire\Reports\ReportMonitoring;
use App\Models\Asrama;
use App\Models\DailyTask;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\ReportStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ReportMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_filters_pembina_category_and_preserves_access_and_other_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = Asrama::create(['name' => 'A', 'code' => 'A']);
        $b = Asrama::create(['name' => 'B', 'code' => 'B']);
        $putra = User::factory()->create(['name' => 'Putra Uji', 'role' => 'musyrif']);
        $putri = User::factory()->create(['name' => 'Putri Uji', 'role' => 'musyrifah']);
        foreach ([[$putra, $a], [$putri, $b]] as [$user, $dorm]) {
            $user->asramas()->attach($dorm, ['assignment_role' => $user->role, 'start_date' => today()->toDateString()]);
            app(DailyReportService::class)->save($user, ['report_date' => today()->toDateString(), 'status' => 'submitted', 'completed' => []]);
        }
        $this->actingAs($admin)->get('/reports/monitoring')->assertOk()->assertSee('Kategori pembina');
        Livewire::actingAs($admin)->test(ReportMonitoring::class)->assertViewHas('reports', fn ($reports) => $reports->total() === 2)
            ->call('setPage', 2)->set('pembinaRole', 'musyrif')->assertSet('paginators.page', 1)
            ->assertSee('Putra Uji')->assertDontSee('Putri Uji')
            ->set('pembinaRole', 'musyrifah')->assertSee('Putri Uji')->assertDontSee('Putra Uji')
            ->set('status', 'draft')->assertViewHas('reports', fn ($reports) => $reports->total() === 0)
            ->set('status', '')->set('pembinaRole', '')->assertViewHas('reports', fn ($reports) => $reports->total() === 2)
            ->set('pembinaRole', 'invalid')->assertHasErrors('pembinaRole');
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $coordinator->asramas()->attach($a, ['assignment_role' => 'koordinator', 'start_date' => today()->toDateString()]);
        Livewire::actingAs($coordinator)->test(ReportMonitoring::class)->set('pembinaRole', 'musyrifah')->assertViewHas('reports', fn ($reports) => $reports->total() === 0);
        $this->actingAs($putra)->get('/reports/monitoring')->assertForbidden();
    }

    public function test_coordinator_scope_monthly_recap_export_and_duplicate_prevention(): void
    {
        $this->travelTo(Carbon::parse('2026-11-13 12:00:00'));
        $a = Asrama::create(['name' => 'A', 'code' => 'A']);
        $b = Asrama::create(['name' => 'B', 'code' => 'B']);
        $u = User::factory()->create();
        $u->asramas()->attach($a, ['assignment_role' => 'musyrifah', 'start_date' => '2026-11-01']);
        $v = User::factory()->create();
        $v->asramas()->attach($b, ['assignment_role' => 'musyrifah', 'start_date' => '2026-11-01']);
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $coordinator->asramas()->attach($a, ['assignment_role' => 'koordinator', 'start_date' => '2026-11-01']);
        DailyTask::create(['title' => 'Subuh', 'sort_order' => 1]);
        $service = app(DailyReportService::class);
        $data = ['report_date' => '2026-11-13', 'status' => 'submitted', 'completed' => []];
        $r = $service->save($u, $data);
        $outside = $service->save($v, $data);
        $this->assertTrue(Gate::forUser($coordinator)->allows('review', $r));
        $this->assertFalse(Gate::forUser($coordinator)->allows('view', $outside));
        $rows = app(ReportStatisticsService::class)->monthly($coordinator, '2026-11');
        $this->assertCount(1, $rows);
        $this->assertSame(12, $rows[0]['missing']);
        $this->assertSame(1, $rows[0]['submitted']);
        $this->assertSame(0.0, $rows[0]['average']);
        $this->actingAs($coordinator)->get('/reports/monthly')->assertOk();
        $response = $this->get('/reports/export?month=2026-11');
        $response->assertOk()->assertDownload('sistem-asrama-2026-11.xlsx');
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $xml = html_entity_decode($zip->getFromName('xl/worksheets/sheet1.xml'), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $this->assertStringContainsString($u->name, $xml);
        $this->assertStringNotContainsString($v->name, $xml);
        $zip->close();
        $this->actingAs($u)->get('/reports/export?month=2026-11')->assertForbidden();
        $this->expectException(ValidationException::class);
        $service->save($u, $data);
    }
}
