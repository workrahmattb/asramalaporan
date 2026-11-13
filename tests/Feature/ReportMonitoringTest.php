<?php

namespace Tests\Feature;

use App\Models\Asrama;
use App\Models\DailyTask;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\ReportStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportMonitoringTest extends TestCase
{
    use RefreshDatabase;

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
