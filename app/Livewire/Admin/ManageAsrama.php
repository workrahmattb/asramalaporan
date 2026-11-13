<?php

namespace App\Livewire\Admin;

use App\Models\Asrama;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ManageAsrama extends Component
{
    #[Locked]
    public ?int $editId = null;

    public bool $showEditor = false;

    public string $name = '';

    public string $code = '';

    public string $unit = '';

    public string $gender = '';

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
        $m = Asrama::findOrFail($id);
        $this->editId = $id;
        foreach (['name', 'code', 'unit', 'gender'] as $field) {
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
        $data = $this->validate(['name' => 'required|string|max:255', 'code' => 'required|string|max:50|unique:asramas,code,'.($this->editId ?? 'NULL'), 'unit' => 'nullable|string|max:255', 'gender' => 'nullable|in:putra,putri', 'is_active' => 'boolean']);
        Asrama::updateOrCreate(['id' => $this->editId], $data);
        $this->cancel();
        session()->flash('success', 'Data berhasil disimpan.');
    }

    public function render(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('livewire.admin.asrama', ['records' => Asrama::orderBy('id')->get()])->layout('layouts.simus');
    }
}
