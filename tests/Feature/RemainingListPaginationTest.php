<?php

namespace Tests\Feature;

use App\Livewire\Admin\ManageAsrama;
use App\Livewire\Admin\ManageMusyrifah;
use App\Livewire\Admin\ManageTasks;
use App\Livewire\Reports\MonthlyRecap;
use App\Models\Asrama;
use App\Models\DailyTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class RemainingListPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_asramas_sort_by_name_and_search_resets_pagination_without_editor_clearing_search(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (range(11, 1) as $i) {
            Asrama::create(['name' => sprintf('Asrama %02d', $i), 'code' => 'A'.$i, 'unit' => $i === 11 ? 'Gedung Timur' : 'Gedung Barat']);
        }
        $last = Asrama::where('code', 'A11')->firstOrFail();
        $component = Livewire::actingAs($admin)->test(ManageAsrama::class)
            ->assertSeeInOrder(['Asrama 01', 'Asrama 02', 'Asrama 10'])->assertDontSee('Asrama 11')
            ->assertViewHas('records', fn ($records) => $records->perPage() === 10 && $records->total() === 11)
            ->call('setPage', 2)->assertSee('Asrama 11')->assertDontSee('Asrama 01')
            ->set('search', '  Asrama 11  ')->assertSet('paginators.page', 1)->assertViewHas('records', fn ($records) => $records->total() === 1)
            ->call('edit', $last->id)->call('cancel')->assertSet('search', '  Asrama 11  ')
            ->call('edit', $last->id)->set('unit', 'Gedung Timur Baru')->call('save')->assertHasNoErrors()->assertSet('search', '  Asrama 11  ')
            ->set('search', 'A11')->assertViewHas('records', fn ($records) => $records->total() === 1)
            ->set('search', 'Timur Baru')->assertViewHas('records', fn ($records) => $records->total() === 1)
            ->set('search', 'Tidak ditemukan')->assertSee('Tidak ada asrama sesuai pencarian.')->assertViewHas('records', fn ($records) => $records->total() === 0);
        $component->set('search', '')->assertViewHas('records', fn ($records) => $records->total() === 11);
    }

    public function test_pembina_paginates_and_filters_reset_page_while_popup_preserves_it(): void
    {
        $admin = User::factory()->create(['name' => 'ZZ Admin', 'role' => 'admin']);
        $asrama = Asrama::create(['name' => 'Asrama A', 'code' => 'A', 'gender' => 'putra']);
        $last = null;
        foreach (range(1, 11) as $i) {
            $last = User::factory()->create(['name' => sprintf('Pembina %02d', $i), 'role' => 'musyrif']);
            $last->asramas()->attach($asrama, ['assignment_role' => 'musyrif', 'start_date' => today()->toDateString()]);
        }
        $component = Livewire::actingAs($admin)->test(ManageMusyrifah::class)->assertSee('Pembina 01')->assertDontSee('Pembina 11')
            ->assertViewHas('users', fn ($users) => $users->perPage() === 10 && $users->total() === 12)
            ->call('setPage', 2)->assertSee('Pembina 11')->assertDontSee('Pembina 01')
            ->call('edit', $last->id)->call('cancel')->assertSet('paginators.page', 2)->assertSee('Pembina 11')
            ->set('filterAsrama', (string) $asrama->id)->assertSet('paginators.page', 1)->assertViewHas('users', fn ($users) => $users->total() === 11);
        $component->call('setPage', 2)->set('gender', 'putri')->assertSet('paginators.page', 1)->assertViewHas('users', fn ($users) => $users->total() === 0);
    }

    public function test_daily_tasks_paginate_and_editing_a_task_keeps_current_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $last = null;
        foreach (range(1, 11) as $i) {
            $last = DailyTask::create(['title' => sprintf('Kegiatan %02d', $i), 'sort_order' => $i]);
        }
        $component = Livewire::actingAs($admin)->test(ManageTasks::class)->assertSee('Kegiatan 01')->assertDontSee('Kegiatan 11')
            ->assertViewHas('records', fn ($records) => $records->perPage() === 10 && $records->total() === 11)
            ->call('setPage', 2)->assertSee('Kegiatan 11')->assertDontSee('Kegiatan 01')
            ->call('edit', $last->id)->set('title', 'Kegiatan diperbarui')->call('save')->assertHasNoErrors()->assertSet('paginators.page', 2)->assertSee('Kegiatan diperbarui');
        $component->call('create')->call('cancel')->assertSet('paginators.page', 2);
        $this->assertDatabaseCount('daily_tasks', 11);
    }

    public function test_monthly_recap_paginates_for_admin_and_pembina_but_excel_contains_all_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $pembina = User::factory()->create(['role' => 'musyrif']);
        $first = null;
        foreach (range(1, 12) as $i) {
            $asrama = Asrama::create(['name' => sprintf('Asrama %02d', $i), 'code' => 'A'.$i]);
            $first ??= $asrama;
            $pembina->asramas()->attach($asrama, ['assignment_role' => 'musyrif', 'start_date' => today()->toDateString()]);
        }
        foreach ([$admin, $pembina] as $viewer) {
            $component = Livewire::actingAs($viewer)->test(MonthlyRecap::class)
                ->assertViewHas('rows', fn ($rows) => $rows->perPage() === 10 && $rows->count() === 10 && $rows->total() === 12)
                ->assertSee('Pagination Navigation')->assertSee('wire:click="nextPage(', false)
                ->call('setPage', 2)->assertViewHas('rows', fn ($rows) => $rows->count() === 2 && $rows->first()['asrama'] === 'Asrama 11')
                ->set('asramaId', (string) $first->id)->assertSet('paginators.page', 1)->assertViewHas('rows', fn ($rows) => $rows->total() === 1);
            $component->set('asramaId', '')->call('setPage', 2)->set('month', today()->subMonth()->format('Y-m'))->assertSet('paginators.page', 1)->assertViewHas('rows', fn ($rows) => $rows->total() === 0);
        }
        $response = $this->actingAs($admin)->get('/reports/export?month='.today()->format('Y-m'))->assertOk()->assertDownload();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Asrama 12', $xml);
        $this->assertStringContainsString('<row r="13">', $xml);
        $zip->close();
    }
}
