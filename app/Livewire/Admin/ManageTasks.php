<?php

namespace App\Livewire\Admin;

use App\Models\DailyTask;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ManageTasks extends Component
{
    #[Locked]
    public ?int $editId = null;

    public bool $showEditor = false;

    public string $title = '';

    public string $scheduled_time = '';

    public string $sort_order = '';

    public bool $is_active = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function create(): void
    {
        abort_unless(auth()->user()->isAdmin() && auth()->user()->is_active, 403);
        $this->cancel();
        $this->showEditor = true;
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->isAdmin() && auth()->user()->is_active, 403);
        $this->cancel();
        $m = DailyTask::findOrFail($id);
        $this->editId = $id;
        foreach (['title', 'scheduled_time', 'sort_order'] as $field) {
            $this->$field = (string) $m->$field;
        } $this->is_active = $m->is_active;
        $this->showEditor = true;
    }

    public function cancel(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isAdmin() && auth()->user()->is_active, 403);
        $data = $this->validate(['title' => 'required|string|max:255', 'scheduled_time' => 'nullable|string|max:255', 'sort_order' => 'required|integer|min:0', 'is_active' => 'boolean']);
        DailyTask::updateOrCreate(['id' => $this->editId], $data);
        $this->cancel();
        session()->flash('success', 'Data berhasil disimpan.');
    }

    public function render(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('livewire.admin.dailytask', ['records' => DailyTask::orderBy('id')->get()])->layout('layouts.simus');
    }
}
