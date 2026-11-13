<?php

namespace Tests\Feature;

use App\Livewire\Admin\ManageAsrama;
use App\Livewire\Admin\ManageMusyrifah;
use App\Livewire\Admin\ManageTasks;
use App\Models\Asrama;
use App\Models\DailyTask;
use App\Models\User;
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
            ->call('save')->assertHasErrors(['name', 'email', 'password', 'asrama_id'])->assertSet('showEditor', true);
        $this->assertDatabaseCount('users', 1);
        $component->set('name', 'Pembina Baru')->set('email', 'pembina@example.com')
            ->set('password', '123456')->set('role', 'musyrif')->set('asrama_id', (string) $asrama->id)
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
