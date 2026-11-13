<?php

namespace Tests\Feature;

use App\Livewire\Reports\ReportForm;
use App\Models\Asrama;
use App\Models\DailyReport;
use App\Models\DailyTask;
use App\Models\User;
use App\Services\DailyReportService;
use App\Services\ReportStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SimusTest extends TestCase
{
    use RefreshDatabase;

    private function musyrifah(): User
    {
        $u = User::factory()->create();
        $a = Asrama::create(['name' => 'Aisyah', 'code' => uniqid()]);
        $u->asramas()->attach($a, ['assignment_role' => 'musyrifah', 'start_date' => today()]);

        return $u;
    }

    public function test_report_snapshot_and_authorization(): void
    {
        $u = $this->musyrifah();
        $task = DailyTask::create(['title' => 'Subuh', 'sort_order' => 1]);
        $service = app(DailyReportService::class);
        $r = $service->save($u, ['report_date' => today()->toDateString(), 'status' => 'submitted', 'completed' => [$task->id]]);
        $this->assertSame(100.0, $r->percentage);
        $task->update(['title' => 'Diubah', 'is_active' => false]);
        $this->assertSame('Subuh', $r->items->first()->task_title);
        $other = $this->musyrifah();
        $this->assertFalse(Gate::forUser($other)->allows('view', $r));
        $admin = User::factory()->create(['role' => 'admin']);
        $service->review($admin, $r, 'verified', 'Baik');
        $this->assertFalse(Gate::forUser($u)->allows('update', $r->fresh()));
        $service->review($admin, $r->fresh(), 'draft', 'Dibuka');
        $this->assertTrue(Gate::forUser($u)->allows('update', $r->fresh()));
        $this->assertDatabaseCount('report_audits', 3);
    }

    public function test_pages_render_and_admin_access_is_restricted(): void
    {
        $u = $this->musyrifah();
        DailyTask::create(['title' => 'Subuh', 'sort_order' => 1]);
        $this->actingAs($u)->get('/dashboard/musyrifah')->assertOk();
        $this->get('/reports/create')->assertOk();
        $this->get('/reports')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        foreach (['/dashboard/admin', 'admin/users', 'admin/asramas', 'admin/tasks', 'reports/monitoring'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_draft_optional_tasks_and_private_photos(): void
    {
        Storage::fake('local');
        $u = $this->musyrifah();
        DailyTask::create(['title' => 'Subuh', 'sort_order' => 1]);
        $r = app(DailyReportService::class)->save($u, ['report_date' => today()->toDateString(), 'status' => 'draft', 'completed' => []], [UploadedFile::fake()->image('photo.jpg')]);
        $this->assertSame(0.0, $r->percentage);
        $photo = $r->photos()->first();
        $this->actingAs($u)->get('/photos/'.$photo->id)->assertOk();
        $this->actingAs($this->musyrifah())->get('/photos/'.$photo->id)->assertForbidden();
        $this->assertSame(0, app(ReportStatisticsService::class)->summary($u, now()->format('Y-m'))['reports']);
    }

    public function test_livewire_saves_and_detail_renders(): void
    {
        $u = $this->musyrifah();
        DailyTask::create(['title' => 'Subuh', 'sort_order' => 1]);
        $this->actingAs($u);
        $component = Livewire::test(ReportForm::class)->call('save', 'submitted')->assertHasNoErrors()->assertSet('saved', true)->assertRedirect();
        $this->assertTrue($component->effects['redirectUsingNavigate']);
        $r = DailyReport::firstOrFail();
        $this->get('/reports/'.$r->id)->assertOk();
        $this->get('/reports/'.$r->id.'/edit')->assertOk();
    }

    public function test_registration_disabled_and_inactive_login_rejected(): void
    {
        $this->get('/register')->assertNotFound();
        $u = User::factory()->create(['is_active' => false]);
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
