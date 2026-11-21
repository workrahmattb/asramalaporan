<?php

namespace App\Services;

use App\Models\Asrama;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AsramaStudentService
{
    public function assign(User $user, int $asramaId, int $studentId): void
    {
        $this->assignMany($user, $asramaId, [$studentId]);
    }

    /** @param list<int> $studentIds */
    public function assignMany(User $user, int $asramaId, array $studentIds): int
    {
        Gate::forUser($user)->authorize('manage', Santri::class);
        Validator::make(['studentIds' => $studentIds], ['studentIds' => 'required|array|min:1', 'studentIds.*' => 'required|integer|distinct'])->validate();

        return DB::transaction(function () use ($user, $asramaId, $studentIds): int {
            $asrama = Asrama::whereKey($asramaId)->lockForUpdate()->firstOrFail();
            if (! $asrama->is_active) {
                throw ValidationException::withMessages(['asrama_id' => 'Aktifkan asrama sebelum menambahkan santri.']);
            }
            $students = Santri::with('currentPlacement')->whereIn('id', $studentIds)->orderBy('id')->lockForUpdate()->get();
            if ($students->count() !== count($studentIds)) {
                throw ValidationException::withMessages(['studentIds' => 'Sebagian santri tidak ditemukan. Periksa pilihan santri.']);
            }
            foreach ($students as $student) {
                if (! $student->is_active || $student->currentPlacement) {
                    throw ValidationException::withMessages(['studentIds' => $student->name.' sudah ditempatkan atau nonaktif. Batalkan pilihannya dan coba lagi.']);
                }
                if ($asrama->gender && $student->gender && $asrama->gender !== $student->gender) {
                    throw ValidationException::withMessages(['asrama_id' => 'Kategori '.$student->name.' tidak sesuai dengan asrama tujuan.']);
                }
            }
            foreach ($students as $student) {
                app(SantriService::class)->save($user, ['name' => $student->name, 'asrama_id' => $asrama->id], $student->id);
            }

            return $students->count();
        }, 3);
    }

    public function remove(User $user, int $asramaId, int $studentId): void
    {
        Gate::forUser($user)->authorize('manage', Santri::class);
        DB::transaction(function () use ($asramaId, $studentId) {
            Asrama::whereKey($asramaId)->lockForUpdate()->firstOrFail();
            $student = Santri::whereKey($studentId)->lockForUpdate()->firstOrFail();
            if ($student->currentPlacement?->asrama_id !== $asramaId) {
                throw ValidationException::withMessages(['studentId' => 'Penempatan santri sudah berubah. Muat ulang daftar asrama.']);
            }
            $student->placements()->where('asrama_id', $asramaId)->whereDate('start_date', '<=', today())->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()))->update(['end_date' => today()->subDay()->toDateString()]);
        }, 3);
    }
}
