<?php

namespace App\Livewire\Admin;

use App\Models\Asrama;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ManageMusyrifah extends Component
{
    #[Locked]
    public ?int $editId = null;

    public bool $showEditor = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'musyrifah';

    /** @var list<int|string> */
    public array $asrama_ids = [];

    public bool $is_active = true;

    public string $filterAsrama = '';

    public string $gender = '';

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
        $u = User::findOrFail($id);
        $this->editId = $id;
        foreach (['name', 'email', 'role', 'is_active'] as $field) {
            $this->$field = $u->$field;
        } $this->asrama_ids = array_values($u->assignedAsramas()->get()->map(fn (Asrama $asrama) => $asrama->id)->all());
        $this->password = '';
        $this->showEditor = true;
    }

    public function cancel(): void
    {
        $this->resetExcept('filterAsrama', 'gender');
        $this->resetValidation();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->isAdmin() && auth()->user()->is_active, 403);
        $data = $this->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email,'.($this->editId ?? 'NULL'), 'password' => ($this->editId ? 'nullable' : 'required').'|string', 'role' => 'required|in:super_admin,admin,koordinator,musyrif,musyrifah', 'asrama_ids' => (in_array($this->role, ['admin', 'super_admin']) ? 'present' : 'required').'|array', 'asrama_ids.*' => 'required|integer|distinct|exists:asramas,id', 'is_active' => 'boolean']);
        if ($this->editId === auth()->id() && (! $this->is_active || ! in_array($this->role, ['admin', 'super_admin']))) {
            $this->addError('role', 'Akun admin sendiri harus tetap aktif sebagai admin.');

            return;
        }
        if (str_ends_with(strtolower($this->email), '.invalid')) {
            $this->addError('email', 'Ganti identifier sementara dengan alamat email asli yang telah dikonfirmasi.');

            return;
        }
        $existing = $this->editId ? User::findOrFail($this->editId) : null;
        if ($existing && ! $existing->credentials_ready && ! $this->password) {
            $this->addError('password', 'Tetapkan password baru sebelum mengaktifkan login akun sementara.');

            return;
        }
        DB::transaction(function () use ($data) {
            $u = $this->editId ? User::whereKey($this->editId)->lockForUpdate()->firstOrFail() : new User;
            $u->fill(Arr::except($data, ['password', 'asrama_ids']));
            if ($this->password) {
                $u->password = $this->password;
            } $u->email_verified_at = $u->email_verified_at ?? now();
            $u->forceFill(['credentials_ready' => true]);
            if ($this->password) {
                $u->must_change_password = true;
            }
            $u->save();
            $selected = array_map('intval', $this->asrama_ids);
            $current = $u->asramas()->withPivot('id')->whereDate('asrama_user.start_date', '<=', today())->where(fn ($q) => $q->whereNull('asrama_user.end_date')->orWhereDate('asrama_user.end_date', '>=', today()))->get();
            foreach ($current as $placement) {
                if (! in_array($placement->id, $selected, true) || $placement->getAttribute('pivot')->assignment_role !== $this->role) {
                    DB::table('asrama_user')->where('id', $placement->getAttribute('pivot')->id)->update(['end_date' => today()->subDay()->toDateString()]);
                }
            }
            foreach ($selected as $id) {
                if (! $current->contains(fn ($a) => $a->id === $id && $a->getAttribute('pivot')->assignment_role === $this->role)) {
                    $u->asramas()->attach($id, ['assignment_role' => $this->role, 'start_date' => today()->toDateString()]);
                }
            }
        });
        $this->cancel();
        session()->flash('success', 'Akun dan penempatan disimpan.');
    }

    public function render(): View
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return view('livewire.admin.users', ['users' => User::with('asramas')->when($this->filterAsrama, fn ($q) => $q->whereHas('asramas', fn ($a) => $a->where('asramas.id', $this->filterAsrama)->whereNull('asrama_user.end_date')))->when($this->gender, fn ($q) => $q->whereHas('asramas', fn ($a) => $a->where('gender', $this->gender)->whereNull('asrama_user.end_date')))->orderBy('name')->get(), 'asramas' => Asrama::where('is_active', true)->get()])->layout('layouts.simus');
    }
}
