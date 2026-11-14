<?php

namespace Tests\Feature;

use App\Livewire\Admin\ManageAssessmentPeriods;
use App\Livewire\Admin\ManageRaporMasters;
use App\Livewire\Assessments\AspectGradeForm;
use App\Models\AcademicYear;
use App\Models\Asrama;
use App\Models\AssessmentAspect;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Santri;
use App\Models\SantriAssessment;
use App\Models\User;
use App\Services\AspectGradeService;
use App\Services\SantriAssessmentService;
use App\Services\SantriService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class AspectGradeFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pembina;

    private Asrama $asrama;

    private AssessmentPeriod $period;

    private AssessmentAspect $aspect;

    private AssessmentAspect $secondAspect;

    private Santri $first;

    private Santri $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->pembina = User::factory()->create(['role' => 'musyrif']);
        $this->asrama = Asrama::create(['name' => 'Asrama A', 'code' => 'A', 'gender' => 'putra']);
        $this->pembina->asramas()->attach($this->asrama, ['assignment_role' => 'musyrif', 'start_date' => today()->toDateString()]);
        $year = AcademicYear::factory()->create(['name' => '2026/2027']);
        $this->period = AssessmentPeriod::create(['name' => 'Ganjil 2026/2027', 'academic_year_id' => $year->id, 'semester' => 'Ganjil', 'is_active' => true, 'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(), 'rating_labels' => ['A', 'B', 'C', 'D']]);
        $subject = AssessmentSubject::factory()->create(['title' => 'Adab', 'indicator' => 'Berbicara dengan santun.']);
        $this->aspect = $this->period->aspects()->create(['assessment_subject_id' => $subject->id, 'title' => $subject->title, 'indicator' => $subject->indicator, 'sort_order' => 1]);
        $this->secondAspect = $this->period->aspects()->create(['title' => 'Ibadah', 'indicator' => 'Menjaga ibadah.', 'sort_order' => 2]);
        $this->first = $this->student('Ahmad', '0012345678');
        $this->second = $this->student('Zaid');
    }

    private function student(string $name, ?string $nisn = null): Santri
    {
        return app(SantriService::class)->save($this->admin, ['name' => $name, 'nisn' => $nisn, 'asrama_id' => $this->asrama->id]);
    }

    private function report(Santri $student): SantriAssessment
    {
        return SantriAssessment::where('santri_id', $student->id)->where('assessment_period_id', $this->period->id)->firstOrFail();
    }

    public function test_batch_grades_preserve_other_aspects_and_snapshot_indicator(): void
    {
        $service = app(AspectGradeService::class);
        $service->save($this->pembina, $this->asrama, $this->aspect, [$this->first->id => ['rating' => 'A', 'version' => null], $this->second->id => ['rating' => 'B', 'version' => null]]);
        $report = $this->report($this->first);
        $this->assertSame('Berbicara dengan santun.', $report->items->first()->indicator);
        $service->save($this->pembina, $this->asrama, $this->secondAspect, [$this->first->id => ['rating' => 'C', 'version' => $report->version]]);
        $report->refresh();
        $this->assertSame(['A', 'C'], $report->items->pluck('rating')->all());
        $this->aspect->update(['indicator' => 'Indikator baru']);
        $this->assertSame('Berbicara dengan santun.', $report->items->first()->indicator);
        $service->save($this->pembina, $this->asrama, $this->aspect, [$this->first->id => ['rating' => '', 'version' => $report->version]]);
        $this->assertSame([null, 'C'], $report->fresh()->items->pluck('rating')->all());
        $this->assertSame('B', $this->report($this->second)->items->first()->rating);
    }

    public function test_blank_ungraded_student_is_not_created_and_invalid_grade_rolls_back_batch(): void
    {
        $service = app(AspectGradeService::class);
        $service->save($this->pembina, $this->asrama, $this->aspect, [$this->first->id => ['rating' => '', 'version' => null]]);
        $this->assertDatabaseCount('santri_assessments', 0);
        try {
            $service->save($this->pembina, $this->asrama, $this->aspect, [$this->first->id => ['rating' => 'A', 'version' => null], $this->second->id => ['rating' => 'X', 'version' => null]]);
            $this->fail('Invalid grade must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ratings', $exception->errors());
        }
        $this->assertDatabaseCount('santri_assessments', 0);
        $this->assertDatabaseCount('assessment_audits', 0);
    }

    public function test_stale_row_rolls_back_earlier_rows_without_overwriting_another_pembina(): void
    {
        $service = app(AspectGradeService::class);
        $service->save($this->pembina, $this->asrama, $this->aspect, [$this->first->id => ['rating' => 'A', 'version' => null], $this->second->id => ['rating' => 'B', 'version' => null]]);
        $first = $this->report($this->first);
        $second = $this->report($this->second);
        $service->save($this->pembina, $this->asrama, $this->aspect, [$this->second->id => ['rating' => 'C', 'version' => $second->version]]);
        try {
            $service->save($this->pembina, $this->asrama, $this->aspect, [$this->first->id => ['rating' => 'D', 'version' => $first->version], $this->second->id => ['rating' => 'D', 'version' => $second->version]]);
            $this->fail('Stale row must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ratings', $exception->errors());
        }
        $this->assertSame('A', $first->fresh()->items->first()->rating);
        $this->assertSame($first->version, $first->fresh()->version);
        $this->assertSame('C', $second->fresh()->items->first()->rating);
        $this->assertDatabaseCount('assessment_audits', 3);
    }

    public function test_scope_and_role_are_enforced_for_input_and_export_routes(): void
    {
        $outside = User::factory()->create(['role' => 'musyrifah']);
        $this->actingAs($outside)->get(route('santri.grades', [$this->asrama, $this->aspect]))->assertForbidden();
        $this->get(route('santri.leger', [$this->asrama, $this->period]))->assertForbidden();
        $this->get(route('santri.print-asrama', [$this->asrama, $this->period]))->assertForbidden();
        $coordinator = User::factory()->create(['role' => 'koordinator']);
        $coordinator->asramas()->attach($this->asrama, ['assignment_role' => 'koordinator', 'start_date' => today()->toDateString()]);
        $this->actingAs($coordinator)->get(route('santri.grades', [$this->asrama, $this->aspect]))->assertForbidden();
        $this->get('/admin/assessment-periods')->assertForbidden();
        $other = Asrama::create(['name' => 'B', 'code' => 'B']);
        $student = app(SantriService::class)->save($this->admin, ['name' => 'Luar cakupan', 'asrama_id' => $other->id]);
        $this->expectException(AuthorizationException::class);
        app(AspectGradeService::class)->save($this->pembina, $this->asrama, $this->aspect, [$student->id => ['rating' => 'A', 'version' => null]]);
    }

    public function test_component_paginates_saves_rows_and_rejects_forged_student_keys(): void
    {
        foreach (range(1, 10) as $i) {
            $this->student(sprintf('Santri %02d', $i));
        }
        $component = Livewire::actingAs($this->pembina)->test(AspectGradeForm::class, ['asrama' => $this->asrama, 'aspect' => $this->aspect])
            ->assertSee('Ahmad')->assertDontSee('Zaid')
            ->set('ratings.'.$this->first->id, 'A')->call('save')->assertHasNoErrors()->assertDispatched('grades-saved');
        $this->assertSame('A', $this->report($this->first)->items->first()->rating);
        $component->call('goToPage', 2)->assertSee('Zaid')->assertDontSee('Ahmad')
            ->set('ratings.'.$this->first->id, 'B')->call('save')->assertHasErrors('ratings');
        $this->assertSame('A', $this->report($this->first)->items->first()->rating);
    }

    public function test_inactive_period_prevents_saving_an_already_open_form(): void
    {
        $component = Livewire::actingAs($this->pembina)->test(AspectGradeForm::class, ['asrama' => $this->asrama, 'aspect' => $this->aspect]);
        $component->set('ratings.'.$this->first->id, 'A');
        $this->period->update(['is_active' => false]);
        $component->call('save')->assertForbidden();
        $this->assertDatabaseCount('santri_assessments', 0);
    }

    public function test_batch_values_can_be_completed_submitted_reviewed_and_exported(): void
    {
        $bulk = app(AspectGradeService::class);
        $bulk->save($this->pembina, $this->asrama, $this->aspect, [$this->first->id => ['rating' => 'A', 'version' => null]]);
        $report = $this->report($this->first);
        $bulk->save($this->pembina, $this->asrama, $this->secondAspect, [$this->first->id => ['rating' => 'B', 'version' => $report->version]]);
        $report->refresh();
        $service = app(SantriAssessmentService::class);
        $report = $service->save($this->pembina, $this->first, $this->period, ['status' => 'submitted', 'ratings' => $report->items->mapWithKeys(fn ($item) => [$item->assessment_aspect_id => $item->rating])->all(), 'development' => 'Semakin mandiri.', 'next_focus' => 'Menjaga konsistensi.'], $report->version);
        $service->review($this->admin, $report, 'approved', 'Baik.');
        $bulk->save($this->pembina, $this->asrama, $this->aspect, [$this->second->id => ['rating' => 'C', 'version' => null]]);
        $this->second->update(['nisn' => '0098765432']);
        $this->actingAs($this->pembina)->get(route('santri.print-asrama', [$this->asrama, $this->period]))->assertOk()->assertSee('Ahmad')->assertSee('Berbicara dengan santun.')->assertDontSee('Zaid');
        $response = $this->get(route('santri.leger', [$this->asrama, $this->period]))->assertOk()->assertDownload();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('0012345678', $xml);
        $this->assertStringContainsString('Zaid', $xml);
        $this->assertStringContainsString('Disetujui', $xml);
        $this->assertStringNotContainsString('0098765432', $xml);
        $zip->close();
        $this->expectException(AuthorizationException::class);
        $bulk->save($this->pembina, $this->asrama, $this->aspect, [$this->first->id => ['rating' => 'D', 'version' => $report->fresh()->version]]);
    }

    public function test_master_edits_do_not_rewrite_period_snapshot_and_year_deactivation_closes_input(): void
    {
        $subject = AssessmentSubject::findOrFail($this->aspect->assessment_subject_id);
        Livewire::actingAs($this->admin)->test(ManageRaporMasters::class)->call('edit', 'subject', $subject->id)
            ->set('title', 'Adab baru')->set('indicator', 'Indikator baru')->call('save')->assertHasNoErrors();
        $this->assertSame('Adab', $this->aspect->fresh()->title);
        $this->assertSame('Berbicara dengan santun.', $this->aspect->fresh()->indicator);
        Livewire::actingAs($this->admin)->test(ManageRaporMasters::class)->call('edit', 'year', $this->period->academic_year_id)
            ->set('is_active', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($this->period->fresh()->isOpen());
    }

    public function test_admin_creates_semester_from_master_and_rejects_duplicate_semester(): void
    {
        $subject = AssessmentSubject::factory()->create(['title' => 'Tahfiz', 'indicator' => 'Hafalan terjaga.']);
        $component = Livewire::actingAs($this->admin)->test(ManageAssessmentPeriods::class)->call('create')
            ->set('name', 'Genap 2026/2027')->set('semester', 'Genap')->set('subjectIds', [$subject->id])
            ->set('is_active', true)->call('save')->assertHasNoErrors();
        $period = AssessmentPeriod::where('semester', 'Genap')->firstOrFail();
        $this->assertSame(['A', 'B', 'C', 'D'], $period->rating_labels);
        $this->assertSame('Hafalan terjaga.', $period->aspects->first()->indicator);
        $this->assertFalse($this->period->fresh()->is_active);
        $component->call('create')->set('name', 'Duplikat')->set('semester', 'Genap')->call('save')->assertHasErrors('semester');
    }

    public function test_activating_new_year_closes_old_semester_without_changing_saved_grades(): void
    {
        app(AspectGradeService::class)->save($this->pembina, $this->asrama, $this->aspect, [$this->first->id => ['rating' => 'A', 'version' => null]]);
        Livewire::actingAs($this->admin)->test(ManageRaporMasters::class)->call('create', 'year')
            ->set('title', '2027/2028')->set('is_active', true)->call('save')->assertHasNoErrors();
        $this->assertSame('2027/2028', AcademicYear::where('is_active', true)->sole()->name);
        $this->assertFalse($this->period->fresh()->is_active);
        $this->assertSame('A', $this->report($this->first)->items->first()->rating);
        $this->assertSame('Ganjil 2026/2027', $this->report($this->first)->period_name);
        $subject = AssessmentSubject::factory()->create(['is_active' => false]);
        Livewire::actingAs($this->admin)->test(ManageAssessmentPeriods::class)->call('create')
            ->set('name', 'Semester baru')->set('subjectIds', [$subject->id])->call('save')->assertHasErrors('subjectIds');
    }
}
