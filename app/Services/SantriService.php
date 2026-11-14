<?php

namespace App\Services;

use App\Models\Asrama;
use App\Models\GuidanceNote;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SantriService
{
    /** @param array<string,mixed> $data */
    public function save(User $user, array $data, ?int $id = null): Santri
    {
        Gate::forUser($user)->authorize('manage', Santri::class);
        foreach (['nisn', 'gender', 'asrama_id', 'birth_place', 'birth_date', 'father_name', 'mother_name', 'father_phone', 'mother_phone'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]) === '' ? null : trim($data[$field]);
            }
        }
        $valid = Validator::make($data, [
            'name' => 'required|string|max:255',
            'nisn' => 'nullable|string|max:100|unique:santris,nisn,'.($id ?? 'NULL'),
            'gender' => 'nullable|in:putra,putri',
            'asrama_id' => 'nullable|integer|exists:asramas,id',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'father_name' => 'nullable|string|max:255',
            'mother_name' => 'nullable|string|max:255',
            'father_phone' => 'nullable|string|max:50',
            'mother_phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ])->validate();

        return DB::transaction(function () use ($valid, $id) {
            $asrama = ! empty($valid['asrama_id']) ? Asrama::whereKey($valid['asrama_id'])->lockForUpdate()->firstOrFail() : null;
            $santri = $id ? Santri::whereKey($id)->lockForUpdate()->firstOrFail() : new Santri;
            $santri->fill(collect($valid)->except('asrama_id')->all());
            if ($asrama) {
                if (! $asrama->is_active || ($asrama->gender && $santri->gender && $asrama->gender !== $santri->gender)) {
                    throw ValidationException::withMessages(['asrama_id' => 'Pilih asrama aktif dengan kategori yang sesuai.']);
                }
                if (! $santri->gender) {
                    $santri->gender = $asrama->gender;
                }
            } elseif (! array_key_exists('asrama_id', $valid) && $santri->currentPlacement) {
                $asrama = $santri->currentPlacement->asrama;
                if ($asrama->gender && $santri->gender && $asrama->gender !== $santri->gender) {
                    throw ValidationException::withMessages(['gender' => 'Kategori harus sesuai dengan penempatan asrama saat ini.']);
                }
            }
            $santri->save();
            if (array_key_exists('asrama_id', $valid)) {
                $placement = $santri->currentPlacement;
                if ($placement?->asrama_id !== $asrama?->id) {
                    $santri->placements()->whereDate('start_date', '<=', today())->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()))->update(['end_date' => today()->subDay()->toDateString()]);
                    if ($asrama) {
                        $santri->placements()->create(['asrama_id' => $asrama->id, 'start_date' => today()->toDateString()]);
                    }
                }
            }

            return $santri->refresh();
        });
    }

    /** @param array<string,mixed> $data */
    public function addNote(User $user, Santri $santri, array $data): GuidanceNote
    {
        Gate::forUser($user)->authorize('addNote', $santri);
        $valid = Validator::make($data, ['note_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'category' => 'required|in:positive,guidance,follow_up', 'content' => 'required|string|max:10000', 'follow_up' => 'nullable|string|max:10000'])->validate();

        return DB::transaction(function () use ($user, $santri, $valid) {
            $santri = Santri::whereKey($santri->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('addNote', $santri);
            $placement = $santri->currentPlacement;
            if (! $placement) {
                throw ValidationException::withMessages(['content' => 'Santri belum memiliki penempatan aktif.']);
            }

            return GuidanceNote::create($valid + ['santri_id' => $santri->id, 'asrama_id' => $placement->asrama_id, 'user_id' => $user->id]);
        });
    }
}
