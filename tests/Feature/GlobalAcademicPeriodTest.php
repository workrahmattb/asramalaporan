<?php

namespace Tests\Feature;

use App\Livewire\ActiveAcademicPeriod;
use App\Livewire\Admin\AcademicSettings;
use App\Livewire\Admin\ManageAssessmentPeriods;
use App\Livewire\Admin\ManageRaporMasters;
use App\Livewire\Assessments\AssessmentMonitoring;
use App\Models\AcademicYear;
use App\Models\Asrama;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\SantriAssessment;
use App\Models\User;
use App\Services\AcademicPeriodService;
use App\Services\SantriService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalAcademicPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function period(AcademicYear $year, string $semester, bool $active = false): AssessmentPeriod
    {
        $period = AssessmentPeriod::create(['academic_year_id' => $year->id, 'semester' => $semester, 'name' => $semester.' '.$year->name, 'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(), 'is_active' => $active, 'rating_labels' => ['A', 'B', 'C', 'D']]);
        $period->aspects()->create(['title' => 'Adab', 'sort_order' => 1]);

        return $period;
    }

    public function test_one_global_selection_is_used_by_every_role_and_persists_between_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = AcademicYear::factory()->create(['name' => '2026/2027']);
        $old = $this->period($first, 'Ganjil', true);
        $year = AcademicYear::factory()->create(['name' => '2027/2028', 'is_active' => false]);
        $new = $this->period($year, 'Genap');
        Livewire::actingAs($admin)->test(AcademicSettings::class)->call('toggleYear', $year->id)->call('toggleSemester', $new->id)->assertHasNoErrors()->assertDispatched('academic-context-updated');
        $this->assertSame($new->id, app(AcademicPeriodService::class)->current()->id);
        $this->assertFalse($old->fresh()->is_active);
        $this->assertFalse($first->fresh()->is_active);
        foreach (['musyrif', 'musyrifah', 'koordinator'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            Livewire::actingAs($user)->test(ActiveAcademicPeriod::class)->assertSee('2027/2028')->assertSee('Genap')->assertDontSee('Atur tahun ajaran');
        }
        Livewire::actingAs($admin)->test(AcademicSettings::class)->assertSee($year->name)->call('toggleSemester', $new->id)->assertHasNoErrors();
        $this->assertNull(app(AcademicPeriodService::class)->current());
        $this->assertDatabaseCount('assessment_periods', 2);
    }

    public function test_mismatched_year_and_semester_are_rejected_without_changing_active_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $first = AcademicYear::factory()->create();
        $old = $this->period($first, 'Ganjil', true);
        $other = AcademicYear::factory()->create(['is_active' => false]);
        $new = $this->period($other, 'Genap');
        try {
            app(AcademicPeriodService::class)->activate($admin, $first->id, $new->id);
            $this->fail('Mismatched semester must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('periodId', $exception->errors());
        }
        $this->assertSame($old->id, app(AcademicPeriodService::class)->current()->id);
        $this->assertFalse($other->fresh()->is_active);
        $this->assertFalse($new->fresh()->is_active);
        $new->aspects()->delete();
        try {
            app(AcademicPeriodService::class)->activate($admin, $other->id, $new->id);
            $this->fail('Semester without aspects must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('periodId', $exception->errors());
        }
        $this->assertSame($old->id, app(AcademicPeriodService::class)->current()->id);
    }

    public function test_non_admin_cannot_change_global_context_and_empty_setup_is_rendered(): void
    {
        $user = User::factory()->create(['role' => 'musyrifah']);
        $this->actingAs($user)->get('/admin/tahun-ajaran')->assertForbidden();
        Livewire::actingAs($user)->test(AcademicSettings::class)->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/tahun-ajaran')->assertOk()->assertSee('Tambah tahun ajaran')->assertSee('Belum ada tahun ajaran');
    }

    public function test_centralized_creation_does_not_silently_activate_year_or_semester(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($admin)->test(ManageRaporMasters::class, ['centralized' => true])->call('create', 'year')->set('title', '2026/2027')->set('is_active', true)->call('save')->assertHasNoErrors();
        $year = AcademicYear::firstOrFail();
        $this->assertFalse($year->is_active);
        $subject = AssessmentSubject::factory()->create();
        Livewire::actingAs($admin)->test(ManageAssessmentPeriods::class, ['centralized' => true])->call('create')->set('name', 'Ganjil 2026/2027')->set('academicYearId', (string) $year->id)->set('subjectIds', [$subject->id])->set('is_active', true)->call('save')->assertHasNoErrors();
        $this->assertFalse(AssessmentPeriod::firstOrFail()->is_active);
        $this->assertNull(app(AcademicPeriodService::class)->current());
    }

    public function test_review_defaults_to_active_period_and_old_reports_remain_in_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $year = AcademicYear::factory()->create();
        $old = $this->period($year, 'Ganjil');
        $active = $this->period($year, 'Genap', true);
        $asrama = Asrama::create(['name' => 'Asrama', 'code' => 'A']);
        $student = app(SantriService::class)->save($admin, ['name' => 'Ahmad', 'asrama_id' => $asrama->id]);
        foreach ([$old, $active] as $period) {
            SantriAssessment::create(['santri_id' => $student->id, 'assessment_period_id' => $period->id, 'asrama_id' => $asrama->id, 'user_id' => $admin->id, 'student_name' => 'Ahmad', 'asrama_name' => 'Asrama', 'period_name' => $period->name, 'status' => 'submitted']);
        }
        $component = Livewire::actingAs($admin)->test(AssessmentMonitoring::class)->assertViewHas('reports', fn ($reports) => $reports->total() === 1 && $reports->first()->assessment_period_id === $active->id);
        $component->set('showHistory', true)->set('periodId', (string) $old->id)->assertViewHas('reports', fn ($reports) => $reports->total() === 1 && $reports->first()->assessment_period_id === $old->id);
        $component->set('showHistory', false)->assertViewHas('reports', fn ($reports) => $reports->first()->assessment_period_id === $active->id);
        $this->assertDatabaseCount('santri_assessments', 2);
    }
}
