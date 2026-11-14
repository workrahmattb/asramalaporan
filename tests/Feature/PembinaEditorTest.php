<?php

namespace Tests\Feature;

use App\Livewire\Admin\ManageAsrama;
use App\Livewire\Admin\ManageMusyrifah;
use App\Livewire\Admin\ManageTasks;
use App\Livewire\Reports\ReportForm;
use App\Models\Asrama;
use App\Models\DailyTask;
use App\Models\User;
use App\Services\ReportStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PembinaEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_validation_save_cancel_and_filters(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $asrama = Asrama::create(['name' => 'Asrama A', 'code' => 'A', 'gender' => 'putra']);
        $this->actingAs($admin);
        $component = Livewire::test(ManageMusyrifah::class)
            ->set('filterAsrama', (string) $asrama->id)->set('gender', 'putra')
            ->call('create')->assertSet('showEditor', true)
            ->call('save')->assertHasErrors(['name', 'email', 'password', 'asrama_ids'])->assertSet('showEditor', true);
        $this->assertDatabaseCount('users', 1);
        $component->set('name', 'Pembina Baru')->set('email', 'pembina@example.com')
            ->set('password', '123456')->set('role', 'musyrif')->set('asrama_ids', [$asrama->id])
            ->call('save')->assertHasNoErrors()->assertSet('showEditor', false)
            ->assertSet('filterAsrama', (string) $asrama->id)->assertSet('gender', 'putra');
        $user = User::where('email', 'pembina@example.com')->firstOrFail();
        $this->assertSame($asrama->id, $user->assignedAsramas()->firstOrFail()->id);
        $hash = $user->password;
        $component->call('edit', $user->id)->assertSet('showEditor', true)->assertSet('name', 'Pembina Baru')->assertSet('password', '')
            ->set('name', 'Jangan Simpan')->call('cancel')->assertSet('showEditor', false)->assertSet('editId', null)->assertSet('name', '');
        $this->assertSame('Pembina Baru', $user->fresh()->name);
        $component->call('edit', $user->id)->set('name', 'Pembina Diperbarui')->call('save')->assertHasNoErrors()->assertSet('showEditor', false);
        $this->assertSame('Pembina Diperbarui', $user->fresh()->name);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('asrama_user', 1);
    }

    public function test_multiple_assignments_are_loaded_and_only_removed_placements_are_closed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = Asrama::create(['name' => 'A', 'code' => 'A', 'gender' => 'putri']);
        $b = Asrama::create(['name' => 'B', 'code' => 'B', 'gender' => 'putri']);
        foreach (['musyrif', 'musyrifah'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $start = today()->subMonth()->toDateString();
            $user->asramas()->attach($a, ['assignment_role' => $role, 'start_date' => $start]);
            $component = Livewire::actingAs($admin)->test(ManageMusyrifah::class)->call('edit', $user->id)
                ->assertSet('asrama_ids', [$a->id])->set('asrama_ids', [$a->id, $b->id])->call('save')->assertHasNoErrors();
            $this->assertCount(2, $user->assignedAsramas()->get());
            $this->assertDatabaseHas('asrama_user', ['user_id' => $user->id, 'asrama_id' => $a->id, 'start_date' => $start, 'end_date' => null]);
            $component->call('edit', $user->id)->assertSet('asrama_ids', [$a->id, $b->id])->call('save')->assertHasNoErrors();
            $this->assertSame(2, $user->asramas()->count());
            $component->call('edit', $user->id)->set('asrama_ids', [$b->id])->call('save')->assertHasNoErrors();
            $this->assertSame([$b->id], $user->assignedAsramas()->pluck('asramas.id')->all());
            $this->assertDatabaseHas('asrama_user', ['user_id' => $user->id, 'asrama_id' => $a->id, 'end_date' => today()->subDay()->toDateString()]);
        }
    }

    public function test_pembina_can_report_each_assigned_dorm_but_cannot_select_another_dorm(): void
    {
        $user = User::factory()->create(['role' => 'musyrif']);
        $a = Asrama::create(['name' => 'A', 'code' => 'A']);
        $b = Asrama::create(['name' => 'B', 'code' => 'B']);
        $outside = Asrama::create(['name' => 'Outside', 'code' => 'OUT']);
        foreach ([$a, $b] as $asrama) {
            $user->asramas()->attach($asrama, ['assignment_role' => $user->role, 'start_date' => today()->toDateString()]);
            Livewire::actingAs($user)->test(ReportForm::class)
                ->set('asrama_id', (string) $asrama->id)->call('save', 'submitted')->assertHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('daily_reports', ['user_id' => $user->id, 'asrama_id' => $asrama->id]);
        }
        Livewire::actingAs($user)->test(ReportForm::class)
            ->set('asrama_id', (string) $outside->id)->call('save', 'draft')->assertHasErrors('report_date')->assertNoRedirect();
        Livewire::actingAs($user)->test(ReportForm::class)
            ->set('asrama_id', (string) $a->id)->call('save', 'draft')->assertHasErrors('report_date');
        $this->assertDatabaseCount('daily_reports', 2);
        $daily = app(ReportStatisticsService::class)->daily($user, today()->toDateString());
        $this->assertSame(1, $daily['submitted']);
        $this->assertCount(0, $daily['missing']);
    }

    public function test_asrama_and_task_modals_keep_invalid_data_open_and_close_after_save(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $cases = [
            [ManageAsrama::class, Asrama::class, ['name' => 'Asrama Uji', 'code' => 'TEST', 'gender' => 'putri'], 'name'],
            [ManageTasks::class, DailyTask::class, ['title' => 'Kegiatan Uji', 'sort_order' => '1'], 'title'],
        ];
        foreach ($cases as [$componentClass, $modelClass, $fields, $titleField]) {
            $component = Livewire::test($componentClass)->call('create')->assertSet('showEditor', true)
                ->call('save')->assertHasErrors([$titleField])->assertSet('showEditor', true);
            foreach ($fields as $field => $value) {
                $component->set($field, $value);
            }
            $component->call('save')->assertHasNoErrors()->assertSet('showEditor', false);
            $record = $modelClass::firstOrFail();
            $component->call('edit', $record->id)->assertSet('showEditor', true)->set($titleField, 'Tidak disimpan')->call('cancel')->assertSet('showEditor', false);
            $this->assertSame($fields[$titleField], $record->fresh()->getAttribute($titleField));
            $component->call('edit', $record->id)->set($titleField, 'Data diperbarui')->call('save')->assertHasNoErrors()->assertSet('showEditor', false);
            $this->assertSame('Data diperbarui', $record->fresh()->getAttribute($titleField));
            $this->assertSame(1, $modelClass::count());
        }
    }
}
