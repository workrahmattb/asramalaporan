<?php

namespace Tests\Feature;

use App\Livewire\Admin\ManageAssessmentPeriods;
use App\Livewire\Assessments\AssessmentForm;
use App\Livewire\Assessments\RaporAsrama;
use App\Livewire\Santri\SantriIndex;
use App\Models\AcademicYear;
use App\Models\Asrama;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Santri;
use App\Models\SantriAssessment;
use App\Models\User;
use App\Services\SantriAssessmentService;
use App\Services\SantriService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SantriGuidanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pembina;

    private User $coordinator;

    private Asrama $asrama;

    private Santri $santri;

    private AssessmentPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        AcademicYear::factory()->create();
        AssessmentSubject::factory()->create(['title' => 'Ibadah']);
        AssessmentSubject::factory()->create(['title' => 'Adab']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->pembina = User::factory()->create(['role' => 'musyrif']);
        $this->coordinator = User::factory()->create(['role' => 'koordinator']);
        $this->asrama = Asrama::create(['name' => 'Asrama A', 'code' => 'A', 'gender' => 'putra']);
        foreach ([$this->pembina, $this->coordinator] as $u) {
            $u->asramas()->attach($this->asrama, ['assignment_role' => $u->role, 'start_date' => today()->toDateString()]);
        }
        $this->santri = app(SantriService::class)->save($this->admin, ['name' => 'Santri Uji', 'nisn' => 'TEST-1', 'gender' => 'putra', 'is_active' => true, 'asrama_id' => $this->asrama->id]);
        $this->period = AssessmentPeriod::create(['name' => 'Periode Uji', 'start_date' => today()->subDay()->toDateString(), 'end_date' => today()->addDay()->toDateString(), 'is_active' => true, 'rating_labels' => ['Perlu bimbingan', 'Baik']]);
        foreach (['Ibadah', 'Adab'] as $i => $title) {
            $this->period->aspects()->create(['title' => $title, 'sort_order' => $i]);
        }
    }

    private function data(string $status = 'submitted'): array
    {
        return ['status' => $status, 'ratings' => $this->period->aspects->mapWithKeys(fn ($a) => [$a->id => 'Baik'])->all(), 'item_notes' => [], 'development' => 'Semakin mandiri.', 'next_focus' => 'Menjaga konsistensi.'];
    }

    public function test_scope_protects_student_notes_assessments_and_print(): void
    {
        $outside = User::factory()->create(['role' => 'musyrif']);
        $this->assertFalse(Gate::forUser($outside)->allows('view', $this->santri));
        $this->assertTrue(Gate::forUser($this->coordinator)->allows('view', $this->santri));
        $report = app(SantriAssessmentService::class)->save($this->pembina, $this->santri, $this->period, $this->data());
        $this->actingAs($outside)->get('/santri/'.$this->santri->id)->assertForbidden();
        $this->get('/assessments/'.$report->id)->assertForbidden();
        $this->get('/assessments/'.$report->id.'/print')->assertForbidden();
        $this->actingAs($this->pembina)->get('/assessments/'.$report->id.'/print')->assertForbidden();
        $this->assertFalse(Gate::forUser($this->coordinator)->allows('addNote', $this->santri));
        $this->assertFalse(Gate::forUser($this->pembina)->allows('review', $report));
    }

    public function test_complete_flow_revision_approval_reopen_and_immutable_snapshots(): void
    {
        $service = app(SantriAssessmentService::class);
        $draft = $service->save($this->pembina, $this->santri, $this->period, ['status' => 'draft', 'ratings' => []]);
        $this->assertSame('draft', $draft->status);
        $this->assertCount(2, $draft->items);
        $report = $service->save($this->pembina, $this->santri, $this->period, $this->data(), $draft->version);
        $this->assertFalse(Gate::forUser($this->pembina)->allows('update', $report));
        $service->review($this->coordinator, $report, 'revision', 'Lengkapi deskripsi.');
        $this->assertTrue(Gate::forUser($this->pembina)->allows('update', $report->fresh()));
        $report = $service->save($this->pembina, $this->santri, $this->period, $this->data(), $report->fresh()->version);
        $service->review($this->coordinator, $report, 'approved', 'Sudah diperiksa.');
        $this->actingAs($this->pembina)->get('/assessments/'.$report->id.'/print')->assertOk()->assertSee('Santri Uji')->assertSee('Menjaga konsistensi.');
        $this->santri->update(['name' => 'Nama baru']);
        $this->period->aspects()->first()->update(['title' => 'Aspek baru']);
        $this->assertSame('Santri Uji', $report->fresh()->student_name);
        $this->assertSame('Ibadah', $report->fresh()->items->first()->aspect_title);
        $service->reopen($this->admin, $report->fresh(), 'Periksa kembali fokus pembinaan.');
        $this->assertSame('revision', $report->fresh()->status);
        $this->assertDatabaseCount('assessment_audits', 6);
    }

    public function test_incomplete_submission_rolls_back_and_invalid_ratings_are_rejected(): void
    {
        $service = app(SantriAssessmentService::class);
        try {
            $service->save($this->pembina, $this->santri, $this->period, ['status' => 'submitted', 'ratings' => [], 'development' => 'Ada', 'next_focus' => 'Ada']);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ratings', $e->errors());
        }
        $this->assertDatabaseCount('santri_assessments', 0);
        $this->assertDatabaseCount('santri_assessment_items', 0);
        $data = $this->data();
        $data['ratings'][$this->period->aspects->first()->id] = 'Nilai palsu';
        $this->expectException(ValidationException::class);
        $service->save($this->pembina, $this->santri, $this->period, $data);
    }

    public function test_stale_version_and_duplicate_creation_do_not_overwrite_existing_report(): void
    {
        $service = app(SantriAssessmentService::class);
        $draft = $service->save($this->pembina, $this->santri, $this->period, $this->data('draft'));
        $updated = $service->save($this->pembina, $this->santri, $this->period, $this->data('draft'), $draft->version);
        try {
            $service->save($this->pembina, $this->santri, $this->period, $this->data(), $draft->version);
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ratings', $e->errors());
        }
        try {
            $service->save($this->pembina, $this->santri, $this->period, $this->data());
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertSame('draft', $updated->fresh()->status);
        }
        $this->assertDatabaseCount('santri_assessments', 1);
    }

    public function test_moving_student_preserves_history_and_removes_old_scope(): void
    {
        $note = app(SantriService::class)->addNote($this->pembina, $this->santri, ['note_date' => today()->toDateString(), 'category' => 'positive', 'content' => 'Mulai mandiri.', 'follow_up' => 'Pertahankan.']);
        $other = Asrama::create(['name' => 'Asrama B', 'code' => 'B', 'gender' => 'putra']);
        app(SantriService::class)->save($this->admin, ['name' => 'Santri Uji', 'nisn' => 'TEST-1', 'gender' => 'putra', 'is_active' => true, 'asrama_id' => $other->id], $this->santri->id);
        $this->assertDatabaseCount('santri_placements', 2);
        $this->assertSame($this->asrama->id, $note->asrama_id);
        $this->assertFalse(Gate::forUser($this->pembina)->allows('view', $this->santri->fresh()));
    }

    public function test_closed_period_and_wrong_gender_are_rejected(): void
    {
        $this->period->update(['is_active' => false]);
        try {
            app(SantriAssessmentService::class)->save($this->pembina, $this->santri, $this->period, $this->data());
            $this->fail();
        } catch (ValidationException $e) {
            $this->assertDatabaseCount('santri_assessments', 0);
        }
        $this->expectException(ValidationException::class);
        app(SantriService::class)->save($this->admin, ['name' => 'Salah penempatan', 'nisn' => 'OTHER', 'gender' => 'putri', 'is_active' => true, 'asrama_id' => $this->asrama->id]);
    }

    public function test_rubric_cannot_be_changed_after_assessment_has_started(): void
    {
        app(SantriAssessmentService::class)->save($this->pembina, $this->santri, $this->period, $this->data('draft'));
        $this->actingAs($this->admin);
        Livewire::test(ManageAssessmentPeriods::class)->call('edit', $this->period->id)->set('aspectTitles', 'Aspek pengganti')->call('save')->assertHasErrors('aspectTitles')->assertSet('showEditor', true);
        $this->assertSame('Ibadah', $this->period->aspects()->first()->title);
    }

    public function test_new_pages_render_for_each_role_and_livewire_save_redirects_via_spa(): void
    {
        $this->actingAs($this->admin)->get('/admin/assessment-periods')->assertRedirect(route('admin.academic-settings'));
        $this->get('/admin/tahun-ajaran')->assertOk();
        foreach ([$this->admin, $this->coordinator, $this->pembina] as $viewer) {
            $this->actingAs($viewer)->get('/santri')->assertOk();
            $this->get('/santri/'.$this->santri->id)->assertOk();
        }
        $this->actingAs($this->pembina)->get('/admin/assessment-periods')->assertForbidden();
        $this->get('/assessments')->assertForbidden();
        $this->get('/santri/'.$this->santri->id.'/assess/'.$this->period->id)->assertOk();
        $component = Livewire::actingAs($this->pembina)->test(AssessmentForm::class, ['santri' => $this->santri, 'period' => $this->period]);
        $component->call('save', 'draft')->assertHasNoErrors()->assertRedirect();
        $this->assertTrue($component->effects['redirectUsingNavigate']);
        $r = SantriAssessment::firstOrFail();
        $this->get('/assessments/'.$r->id)->assertOk();
        $this->actingAs($this->coordinator)->get('/assessments')->assertOk();
    }

    public function test_inactive_student_cannot_receive_notes_or_new_assessment(): void
    {
        $this->santri->update(['is_active' => false]);
        $this->assertFalse(Gate::forUser($this->pembina)->allows('addNote', $this->santri));
        $this->expectException(ValidationException::class);
        app(SantriAssessmentService::class)->save($this->pembina, $this->santri, $this->period, $this->data());
    }

    public function test_livewire_validation_preserves_draft_and_santri_modal_can_save_and_edit(): void
    {
        Livewire::actingAs($this->pembina)->test(AssessmentForm::class, ['santri' => $this->santri, 'period' => $this->period])
            ->call('save', 'submitted')->assertHasErrors(['development', 'next_focus'])->assertNoRedirect();
        $this->assertDatabaseCount('santri_assessments', 0);
        $component = Livewire::actingAs($this->admin)->test(SantriIndex::class)
            ->call('create')->call('save')->assertHasErrors(['name'])->assertSet('showEditor', true)
            ->set('name', 'Santri Kedua')->set('asrama_id', (string) $this->asrama->id)
            ->call('save')->assertHasNoErrors()->assertSet('showEditor', false);
        $student = Santri::where('name', 'Santri Kedua')->firstOrFail();
        $this->assertSame($this->asrama->id, $student->currentPlacement->asrama_id);
        $component->call('edit', $student->id)->set('name', 'Santri Diperbarui')->call('save')->assertHasNoErrors();
        $this->assertSame('Santri Diperbarui', $student->fresh()->name);
        $this->assertSame(1, $student->placements()->count());
    }

    public function test_admin_can_create_period_and_student_list_has_ten_rows_per_page(): void
    {
        Livewire::actingAs($this->admin)->test(ManageAssessmentPeriods::class)->call('create')
            ->set('name', 'Periode Berikutnya')->set('is_active', true)->call('save')
            ->assertHasNoErrors()->assertSet('showEditor', false);
        $this->assertDatabaseHas('assessment_periods', ['name' => 'Periode Berikutnya']);
        foreach (range(1, 11) as $i) {
            app(SantriService::class)->save($this->admin, ['name' => sprintf('Santri %02d', $i), 'gender' => 'putra', 'asrama_id' => $this->asrama->id, 'is_active' => true]);
        }
        $component = Livewire::actingAs($this->pembina)->test(SantriIndex::class)
            ->assertSee('Santri 01')->assertDontSee('Santri 11');
        $component->call('setPage', 2)->assertSee('Santri 11')->assertDontSee('Santri 01');
        $component->set('search', 'Santri 01')->assertSee('Santri 01')->assertDontSee('Santri 11');
    }

    public function test_only_name_is_required_and_optional_family_fields_round_trip(): void
    {
        $component = Livewire::actingAs($this->admin)->test(SantriIndex::class)->call('create')
            ->set('name', 'Nama Saja')->call('save')->assertHasNoErrors()->assertSet('showEditor', false);
        $student = Santri::where('name', 'Nama Saja')->firstOrFail();
        $this->assertNull($student->nisn);
        $this->assertNull($student->gender);
        $this->assertNull($student->currentPlacement);
        $this->assertFalse(Gate::forUser($this->pembina)->allows('view', $student));
        $this->actingAs($this->admin)->get('/santri/'.$student->id)->assertOk()->assertSee('Belum diisi');
        $fields = ['nisn' => '0012345678', 'birth_place' => 'Pekanbaru', 'birth_date' => '2012-03-04', 'father_name' => 'Ayah Uji', 'mother_name' => 'Ibu Uji', 'father_phone' => '081234567890', 'mother_phone' => '+62 812-3456-7890'];
        $component->call('edit', $student->id);
        foreach ($fields as $field => $value) {
            $component->set($field, $value);
        }
        $component->call('save')->assertHasNoErrors();
        $component->call('edit', $student->id);
        foreach ($fields as $field => $value) {
            $component->assertSet($field, $value);
        }
        $this->assertSame('0012345678', $student->fresh()->nisn);
        $this->actingAs($this->admin)->get('/santri/'.$student->id)->assertOk()->assertSee('Pekanbaru')->assertSee('04/03/2012')->assertSee('Ayah Uji')->assertSee('081234567890')->assertSee('Ibu Uji');
        foreach (array_keys($fields) as $field) {
            $component->set($field, '');
        }
        $component->call('save')->assertHasNoErrors();
        $student->refresh();
        foreach (array_keys($fields) as $field) {
            $this->assertNull($student->$field);
        }
    }

    public function test_optional_details_are_validated_when_filled_and_placement_can_be_added_later(): void
    {
        $student = app(SantriService::class)->save($this->admin, ['name' => 'Belum ditempatkan']);
        $component = Livewire::actingAs($this->admin)->test(SantriIndex::class)->call('edit', $student->id)
            ->set('birth_date', today()->addDay()->toDateString())->set('nisn', $this->santri->nisn)
            ->call('save')->assertHasErrors(['birth_date', 'nisn'])->assertSet('showEditor', true);
        $component->set('birth_date', '')->set('nisn', '')->set('asrama_id', (string) $this->asrama->id)
            ->call('save')->assertHasNoErrors();
        $student->refresh();
        $this->assertSame('putra', $student->gender);
        $this->assertTrue(Gate::forUser($this->pembina)->allows('view', $student));
        $student->placements()->whereNull('end_date')->update(['end_date' => today()->addDay()->toDateString()]);
        $component->call('edit', $student->id)->set('asrama_id', '')->call('save')->assertHasNoErrors();
        $this->assertNull($student->fresh()->currentPlacement);
        $this->assertSame(1, $student->placements()->count());
    }

    public function test_migration_preserves_existing_identifier_and_assessment_snapshot(): void
    {
        $report = app(SantriAssessmentService::class)->save($this->pembina, $this->santri, $this->period, $this->data());
        $migration = require database_path('migrations/2026_11_14_000002_add_santri_family_details.php');
        // SQLite rebuilds tables for nullable changes; defer child checks during this transaction.
        DB::statement('PRAGMA defer_foreign_keys = ON');
        $migration->down();
        $this->assertDatabaseHas('santris', ['id' => $this->santri->id, 'nis' => 'TEST-1']);
        $migration->up();
        $this->assertDatabaseHas('santris', ['id' => $this->santri->id, 'nisn' => 'TEST-1']);
        $this->assertSame('TEST-1', $report->fresh()->student_nisn);
        $this->assertSame('Santri Uji', $report->fresh()->student_name);
    }

    public function test_pembina_student_menu_is_an_identity_table_and_rapor_uses_admin_period(): void
    {
        $this->santri->update(['birth_place' => 'Dumai', 'birth_date' => '2012-05-03', 'father_name' => 'Ayah Santri', 'mother_name' => 'Ibu Santri']);
        $outside = Asrama::create(['name' => 'Asrama Lain', 'code' => 'OTHER', 'gender' => 'putra']);
        app(SantriService::class)->save($this->admin, ['name' => 'Santri Luar Cakupan', 'asrama_id' => $outside->id]);
        $this->actingAs($this->pembina)->get('/santri')->assertOk()->assertSee('<table', false)
            ->assertSee('Dumai')->assertSee('03/05/2012')->assertSee('Ayah Santri')->assertSee('Ibu Santri')
            ->assertSee('Rapor Asrama')->assertDontSee('Santri Luar Cakupan')
            ->assertDontSee('wire:model.live="periodId"', false)->assertDontSee('Kelengkapan');
        $this->get('/santri/rapor-asrama')->assertOk()->assertSee('Periode Uji')->assertSee('Ibadah')->assertSee('Pilih asrama dan aspek')
            ->assertDontSee('Santri Luar Cakupan')->assertDontSee('wire:model.live="periodId"', false);
        $this->get('/santri/'.$this->santri->id)->assertOk()->assertDontSee('wire:model.live="periodId"', false);
    }

    public function test_admin_controls_single_active_period_and_can_define_subjects_from_review_page(): void
    {
        $this->period->update(['start_date' => '2020-01-01', 'end_date' => '2020-12-31']);
        $this->assertTrue($this->period->isOpen());
        $this->actingAs($this->admin)->get('/assessments')->assertOk()->assertSee('Kelola aspek penilaian', false);
        $this->get('/admin/tahun-ajaran')->assertOk()->assertSee('Tambah tahun ajaran');
        $tahfiz = AssessmentSubject::factory()->create(['title' => 'Tahfiz']);
        $fikih = AssessmentSubject::factory()->create(['title' => 'Fikih']);
        Livewire::actingAs($this->admin)->test(ManageAssessmentPeriods::class)->call('create')
            ->set('subjectIds', [$tahfiz->id, $fikih->id])
            ->set('name', 'Rapor Semester Baru')->set('aspectTitles', "Tahfiz\nFikih")
            ->set('ratingLabels', "1\n2\n3\n4\n5")
            ->set('is_active', true)->call('save')->assertHasNoErrors();
        $this->assertFalse($this->period->fresh()->is_active);
        $this->assertSame(1, AssessmentPeriod::open()->count());
        $this->actingAs($this->pembina)->get('/santri/rapor-asrama?periodId='.$this->period->id)->assertOk()
            ->assertSee('Rapor Semester Baru')->assertSee('Tahfiz')->assertSee('Fikih')->assertDontSee('Periode Uji');
        $this->get('/santri/'.$this->santri->id.'/assess/'.$this->period->id)->assertForbidden();
        $this->actingAs($this->coordinator)->get('/assessments')->assertOk()->assertDontSee('Atur periode & mata pelajaran', false)->assertDontSee('Tambah periode');
    }

    public function test_rapor_has_no_input_when_admin_deactivates_period_and_is_paginated(): void
    {
        foreach (range(1, 11) as $i) {
            app(SantriService::class)->save($this->admin, ['name' => sprintf('Santri %02d', $i), 'asrama_id' => $this->asrama->id]);
        }
        $component = Livewire::actingAs($this->pembina)->test(RaporAsrama::class)
            ->assertSee('Santri 01')->assertDontSee('Santri 11');
        $component->call('setPage', 2)->assertSee('Santri 11')->assertDontSee('Santri 01');
        $this->period->update(['is_active' => false]);
        $component->call('$refresh')->assertSee('Belum ada periode aktif')->assertDontSee('>Isi nilai</a>', false);
        $this->actingAs($this->pembina)->get('/santri/'.$this->santri->id.'/assess/'.$this->period->id)->assertForbidden();
        $this->get('/santri')->assertOk()->assertSee('Santri 01');
    }
}
