<?php

namespace Tests\Feature;

use App\Livewire\Admin\AcademicSettings;
use App\Models\AcademicYear;
use App\Models\Asrama;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\SantriAssessment;
use App\Models\User;
use App\Services\AcademicPeriodService;
use App\Services\AcademicYearService;
use App\Services\SantriService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicYearFlowTest extends TestCase
{
    use RefreshDatabase;

    private function year(User $admin): AcademicYear
    {
        return app(AcademicYearService::class)->save($admin, ['name' => '2026/2027', 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']);
    }

    public function test_creating_year_makes_two_semesters_once_and_single_management_form_is_visible(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AssessmentSubject::factory()->create(['title' => 'Adab']);
        $component = Livewire::actingAs($admin)->test(AcademicSettings::class)->call('create')
            ->call('save')->assertHasErrors(['name', 'start_date', 'end_date'])
            ->set('name', '2026/2027')->set('start_date', '2026-07-01')->set('end_date', '2027-06-30')->call('save')->assertHasNoErrors()->assertSet('showEditor', false);
        $year = AcademicYear::firstOrFail();
        $this->assertCount(2, $year->semesters);
        $ganjil = $year->semesters->firstWhere('semester', 'Ganjil');
        $genap = $year->semesters->firstWhere('semester', 'Genap');
        $this->assertSame($ganjil->end_date->addDay()->toDateString(), $genap->start_date->toDateString());
        $this->assertSame('2027-06-30', $genap->end_date->toDateString());
        $this->assertSame(['A', 'B', 'C', 'D'], $ganjil->rating_labels);
        $this->assertSame('Adab', $ganjil->aspects->first()->title);
        $component->call('edit', $year->id)->call('save')->assertHasNoErrors();
        $this->assertDatabaseCount('assessment_periods', 2);
        $this->actingAs($admin)->get('/admin/tahun-ajaran')->assertOk()->assertSee('Tambah tahun ajaran')->assertDontSee('Tambah periode')->assertDontSee('Terapkan ke seluruh aplikasi')->assertDontSee('Tambah / kelola');
        $this->get('/admin/aspek-penilaian')->assertOk()->assertSee('Master aspek')->assertDontSee('Tambah tahun ajaran');
    }

    public function test_year_then_semester_activation_is_global_and_switching_year_closes_old_semester(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AssessmentSubject::factory()->create();
        $year = $this->year($admin);
        $ganjil = $year->semesters->firstWhere('semester', 'Ganjil');
        $genap = $year->semesters->firstWhere('semester', 'Genap');
        $component = Livewire::actingAs($admin)->test(AcademicSettings::class)->call('toggleSemester', $ganjil->id)->assertHasErrors('year')
            ->call('toggleYear', $year->id)->call('toggleSemester', $ganjil->id)->assertDispatched('academic-context-updated');
        $this->assertSame($ganjil->id, app(AcademicPeriodService::class)->current()->id);
        $component->call('toggleSemester', $genap->id);
        $this->assertSame($genap->id, app(AcademicPeriodService::class)->current()->id);
        $this->assertFalse($ganjil->fresh()->is_active);
        $component->call('toggleYear', $year->id);
        $this->assertNull(app(AcademicPeriodService::class)->current());
    }

    public function test_used_year_cannot_be_deleted_and_reset_preserves_report_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $year = $this->year($admin);
        $period = $year->semesters->first();
        $year->update(['is_active' => true]);
        $period->update(['is_active' => true]);
        $asrama = Asrama::create(['name' => 'A', 'code' => 'A']);
        $student = app(SantriService::class)->save($admin, ['name' => 'Ahmad', 'asrama_id' => $asrama->id]);
        $report = SantriAssessment::create(['santri_id' => $student->id, 'assessment_period_id' => $period->id, 'asrama_id' => $asrama->id, 'user_id' => $admin->id, 'student_name' => 'Ahmad', 'asrama_name' => 'A', 'period_name' => $period->name]);
        Livewire::actingAs($admin)->test(AcademicSettings::class)->call('delete', $year->id)->assertHasErrors('year');
        $this->assertDatabaseHas('santri_assessments', ['id' => $report->id]);
        $result = app(AcademicYearService::class)->resetActive($admin);
        $this->assertSame(1, $result['archived_years']);
        $this->assertFalse($year->fresh()->is_active);
        $this->assertFalse($period->fresh()->is_active);
        $this->assertDatabaseHas('santri_assessments', ['id' => $report->id]);
    }

    public function test_reset_removes_only_unused_active_year_and_empty_legacy_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $year = $this->year($admin);
        $year->update(['is_active' => true]);
        $inactive = AcademicYear::factory()->create(['is_active' => false]);
        $legacy = AssessmentPeriod::create(['name' => 'Lama', 'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(), 'is_active' => true, 'rating_labels' => ['A', 'B', 'C', 'D']]);
        $legacy->aspects()->create(['title' => 'Adab', 'sort_order' => 1]);
        $result = app(AcademicYearService::class)->resetActive($admin);
        $this->assertSame(['deleted_years' => 1, 'deleted_periods' => 1, 'archived_years' => 0], $result);
        $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);
        $this->assertDatabaseHas('academic_years', ['id' => $inactive->id]);
        $this->assertDatabaseCount('assessment_periods', 0);
        $this->assertDatabaseCount('assessment_aspects', 0);
    }
}
