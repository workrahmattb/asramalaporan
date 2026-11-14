<?php

namespace Tests\Feature;

use App\Livewire\Admin\ManageAsramaStudents;
use App\Models\Asrama;
use App\Models\AssessmentPeriod;
use App\Models\Santri;
use App\Models\SantriAssessment;
use App\Models\User;
use App\Services\AsramaStudentService;
use App\Services\SantriService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class AsramaStudentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Asrama $asrama;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->asrama = Asrama::create(['name' => 'Asrama A', 'code' => 'A', 'gender' => 'putra', 'is_active' => true]);
    }

    private function student(string $name, ?int $asramaId = null, ?string $gender = null): Santri
    {
        return app(SantriService::class)->save($this->admin, ['name' => $name, 'asrama_id' => $asramaId, 'gender' => $gender]);
    }

    public function test_existing_student_is_enrolled_and_duplicate_or_stolen_assignment_is_rejected(): void
    {
        $student = $this->student('Ahmad');
        $component = Livewire::actingAs($this->admin)->test(ManageAsramaStudents::class, ['asrama' => $this->asrama])
            ->call('openCandidates')->call('save')->assertHasErrors('studentId')->assertSet('showDropdown', true)
            ->set('studentId', (string) $student->id)->call('save')->assertHasNoErrors()->assertSet('showDropdown', false);
        $this->assertSame($this->asrama->id, $student->fresh()->currentPlacement->asrama_id);
        $this->assertSame('putra', $student->fresh()->gender);
        $component->call('openCandidates')->set('studentId', (string) $student->id)->call('save')->assertHasErrors('studentId');
        $other = Asrama::create(['name' => 'B', 'code' => 'B', 'gender' => 'putra']);
        Livewire::actingAs($this->admin)->test(ManageAsramaStudents::class, ['asrama' => $other])->call('openCandidates')
            ->set('studentId', (string) $student->id)->call('save')->assertHasErrors('studentId');
        $this->assertSame(1, $student->placements()->count());
        $this->assertSame($this->asrama->id, $student->fresh()->currentPlacement->asrama_id);
    }

    public function test_inline_search_select_cancel_and_add_follow_reference_flow_without_creating_identity(): void
    {
        $name = "A' & <em>Santri</em>";
        $student = $this->student($name);
        $student->update(['nisn' => '0012345678']);
        $component = Livewire::actingAs($this->admin)->test(ManageAsramaStudents::class, ['asrama' => $this->asrama])
            ->assertSee('Tambah Santri ke Asrama')->assertDontSee('Sumber data')->assertDontSee('Buat santri baru')
            ->call('openCandidates')->set('candidateSearch', '0012345678')->assertSee($name)
            ->call('selectStudent', $student->id)->assertSet('showDropdown', false)->assertSet('studentId', (string) $student->id)
            ->assertSee($name)->assertDontSee('<em>Santri</em>', false);
        $this->assertDatabaseCount('santri_placements', 0);
        $component->call('clearSelection')->assertSet('studentId', '')->call('save')->assertHasErrors('studentId');
        $component->call('openCandidates')->call('selectStudent', $student->id)->call('save')->assertHasNoErrors()->assertSet('studentId', '');
        $this->assertDatabaseCount('santris', 1);
        $this->assertSame('0012345678', $student->fresh()->nisn);
        $this->assertSame($this->asrama->id, $student->fresh()->currentPlacement->asrama_id);
    }

    public function test_candidates_filter_out_placed_inactive_and_wrong_gender_students_and_server_rechecks(): void
    {
        $available = $this->student('Tersedia');
        $placed = $this->student('Sudah Ditempatkan', $this->asrama->id);
        $wrongGender = $this->student('Putri Lain', null, 'putri');
        $inactive = $this->student('Nonaktif');
        $inactive->update(['is_active' => false]);
        $component = Livewire::actingAs($this->admin)->test(ManageAsramaStudents::class, ['asrama' => $this->asrama])->call('openCandidates')
            ->assertViewHas('candidates', fn ($candidates) => $candidates->pluck('id')->all() === [$available->id]);
        $component->call('selectStudent', $wrongGender->id)->assertHasErrors('studentId')->assertSet('studentId', '');
        $component->set('studentId', (string) $wrongGender->id)->call('save')->assertHasErrors('asrama_id');
        $component->set('studentId', (string) $inactive->id)->call('save')->assertHasErrors('studentId');
        $this->assertNull($wrongGender->fresh()->currentPlacement);
        $this->assertSame($this->asrama->id, $placed->fresh()->currentPlacement->asrama_id);
        $this->asrama->update(['is_active' => false]);
        $component->set('studentId', (string) $available->id)->call('save')->assertHasErrors('asrama_id');
        $this->assertNull($available->fresh()->currentPlacement);
    }

    public function test_removal_closes_placement_but_preserves_identity_notes_and_report_then_allows_reenrollment(): void
    {
        $student = $this->student('Ahmad', $this->asrama->id);
        $note = app(SantriService::class)->addNote($this->admin, $student, ['note_date' => today()->toDateString(), 'category' => 'positive', 'content' => 'Semakin mandiri.']);
        $period = AssessmentPeriod::create(['name' => 'Periode', 'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(), 'rating_labels' => ['A', 'B']]);
        $report = SantriAssessment::create(['santri_id' => $student->id, 'assessment_period_id' => $period->id, 'asrama_id' => $this->asrama->id, 'user_id' => $this->admin->id, 'student_name' => 'Ahmad', 'asrama_name' => $this->asrama->name, 'period_name' => 'Periode']);
        $pembina = User::factory()->create(['role' => 'musyrif']);
        $pembina->asramas()->attach($this->asrama, ['assignment_role' => 'musyrif', 'start_date' => today()->toDateString()]);
        $this->assertTrue(Gate::forUser($pembina)->allows('view', $student));
        Livewire::actingAs($this->admin)->test(ManageAsramaStudents::class, ['asrama' => $this->asrama])->call('confirmRemove', $student->id)->assertSet('confirmingRemoval', true)->call('remove')->assertHasNoErrors()->assertSet('confirmingRemoval', false);
        $this->assertNull($student->fresh()->currentPlacement);
        $this->assertFalse(Gate::forUser($pembina)->allows('view', $student->fresh()));
        $this->assertDatabaseHas('guidance_notes', ['id' => $note->id, 'santri_id' => $student->id]);
        $this->assertDatabaseHas('santri_assessments', ['id' => $report->id]);
        $this->assertDatabaseHas('santri_placements', ['santri_id' => $student->id, 'end_date' => today()->subDay()->toDateString()]);
        app(AsramaStudentService::class)->assign($this->admin, $this->asrama->id, $student->id);
        $this->assertSame(2, $student->placements()->count());
    }

    public function test_member_and_candidate_lists_paginate_independently_and_search_resets_candidate_page(): void
    {
        foreach (range(1, 11) as $i) {
            $this->student(sprintf('Anggota %02d', $i), $this->asrama->id);
            $this->student(sprintf('Calon %02d', $i));
        }
        $component = Livewire::actingAs($this->admin)->test(ManageAsramaStudents::class, ['asrama' => $this->asrama])
            ->assertSee('Anggota 01')->assertDontSee('Anggota 11')->call('setPage', 2)->assertSee('Anggota 11')->assertDontSee('Anggota 01')
            ->call('openCandidates')->assertSee('Calon 01')->assertDontSee('Calon 11')
            ->call('setPage', 2, 'candidatePage')->assertSee('Calon 11')->assertSee('Anggota 11')->assertDontSee('Calon 01')
            ->call('selectStudent', Santri::where('name', 'Calon 11')->value('id'))->assertSee('Santri dipilih')
            ->set('candidateSearch', 'Calon 01')->assertSet('studentId', '')->assertSee('Calon 01')->assertDontSee('Calon 11');
        $component->call('clearSelection')->set('search', 'Anggota 01')->assertSee('Anggota 01')->assertDontSee('Anggota 11');
    }

    public function test_only_active_admin_can_manage_members_and_cross_dorm_removal_is_rejected(): void
    {
        $url = '/admin/asramas/'.$this->asrama->id.'/santri';
        $this->get($url)->assertRedirect('/login');
        foreach (['musyrif', 'musyrifah', 'koordinator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertForbidden();
        }
        Livewire::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => false]))->test(ManageAsramaStudents::class, ['asrama' => $this->asrama])->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/asramas')->assertOk()->assertSee('Kelola santri');
        $this->get($url)->assertOk();
        $other = Asrama::create(['name' => 'B', 'code' => 'B']);
        $student = $this->student('Asrama lain', $other->id);
        Livewire::actingAs($this->admin)->test(ManageAsramaStudents::class, ['asrama' => $this->asrama])->call('confirmRemove', $student->id)->assertHasErrors('studentId')->assertSet('removalId', null)->call('remove')->assertHasErrors('studentId');
        $this->assertSame($other->id, $student->fresh()->currentPlacement->asrama_id);
    }

    public function test_removal_requires_confirmation_and_rechecks_placement_after_dialog_opens(): void
    {
        $student = $this->student('Ahmad', $this->asrama->id);
        $component = Livewire::actingAs($this->admin)->test(ManageAsramaStudents::class, ['asrama' => $this->asrama])
            ->call('remove')->assertHasErrors('studentId')
            ->call('confirmRemove', $student->id)->assertSet('confirmingRemoval', true)->call('cancelRemove')->assertSet('removalId', null);
        $this->assertSame($this->asrama->id, $student->fresh()->currentPlacement->asrama_id);
        $component->call('confirmRemove', $student->id);
        $other = Asrama::create(['name' => 'B', 'code' => 'B']);
        app(SantriService::class)->save($this->admin, ['name' => 'Ahmad', 'asrama_id' => $other->id], $student->id);
        $component->call('remove')->assertHasErrors('studentId');
        $this->assertSame($other->id, $student->fresh()->currentPlacement->asrama_id);
    }
}
