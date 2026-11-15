<?php

namespace Tests\Feature;

use App\Livewire\Santri\SantriIndex;
use App\Models\Asrama;
use App\Models\AssessmentPeriod;
use App\Models\SantriAssessment;
use App\Models\User;
use App\Services\SantriService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SantriDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_must_confirm_and_can_cancel_before_deleting_only_selected_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asrama = Asrama::create(['name' => 'Asrama A', 'code' => 'A']);
        $student = app(SantriService::class)->save($admin, ['name' => 'Santri dipilih', 'asrama_id' => $asrama->id]);
        $other = app(SantriService::class)->save($admin, ['name' => 'Santri lain']);
        $component = Livewire::actingAs($admin)->test(SantriIndex::class)
            ->assertSee('Hapus')->call('delete')->assertHasErrors('deletion')
            ->call('confirmDelete', $student->id)->assertSet('confirmingDeletion', true)
            ->call('cancelDelete')->assertSet('deleteId', null);
        $this->assertModelExists($student);
        $component->call('confirmDelete', $student->id)->call('delete')->assertHasNoErrors()
            ->assertSet('confirmingDeletion', false)->assertSee('Data santri berhasil dihapus.');
        $this->assertModelMissing($student);
        $this->assertModelExists($other);
        $this->assertModelExists($asrama);
        $this->assertDatabaseMissing('santri_placements', ['santri_id' => $student->id]);
    }

    public function test_history_added_after_confirmation_blocks_deletion_and_preserves_placement(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asrama = Asrama::create(['name' => 'Asrama A', 'code' => 'A']);
        $student = app(SantriService::class)->save($admin, ['name' => 'Punya catatan', 'asrama_id' => $asrama->id]);
        $component = Livewire::actingAs($admin)->test(SantriIndex::class)->call('confirmDelete', $student->id);
        $note = app(SantriService::class)->addNote($admin, $student, ['note_date' => today()->toDateString(), 'category' => 'positive', 'content' => 'Catatan lama']);
        $component->call('delete')->assertHasErrors('deletion')->assertSee('Nonaktifkan santri');
        $this->assertModelExists($student);
        $this->assertModelExists($note);
        $this->assertDatabaseHas('santri_placements', ['santri_id' => $student->id, 'asrama_id' => $asrama->id]);
    }

    public function test_any_assessment_including_draft_blocks_deletion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asrama = Asrama::create(['name' => 'Asrama A', 'code' => 'A']);
        $student = app(SantriService::class)->save($admin, ['name' => 'Punya nilai', 'asrama_id' => $asrama->id]);
        $period = AssessmentPeriod::create(['name' => 'Periode', 'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(), 'rating_labels' => ['A', 'B']]);
        $assessment = SantriAssessment::create(['santri_id' => $student->id, 'assessment_period_id' => $period->id, 'asrama_id' => $asrama->id, 'user_id' => $admin->id, 'student_name' => $student->name, 'asrama_name' => $asrama->name, 'period_name' => $period->name]);
        Livewire::actingAs($admin)->test(SantriIndex::class)->call('confirmDelete', $student->id)->call('delete')->assertHasErrors('deletion');
        $this->assertModelExists($student);
        $this->assertModelExists($assessment);
    }

    public function test_pembina_and_inactive_admin_cannot_delete_through_public_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $student = app(SantriService::class)->save($admin, ['name' => 'Tetap ada']);
        foreach ([User::factory()->create(['role' => 'musyrif']), User::factory()->create(['role' => 'admin', 'is_active' => false])] as $viewer) {
            Livewire::actingAs($viewer)->test(SantriIndex::class)->call('confirmDelete', $student->id)->assertForbidden();
            Livewire::actingAs($viewer)->test(SantriIndex::class)->call('delete')->assertForbidden();
        }
        $this->assertModelExists($student);
        auth()->logout();
        $this->get('/santri')->assertRedirect('/login');
    }
}
