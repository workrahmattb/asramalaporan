<?php

namespace App\Services;

use App\Models\Asrama;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AsramaStudentService
{
    public function assign(User $user, int $asramaId, int $studentId): void
    {
        Gate::forUser($user)->authorize('manage', Santri::class);
        DB::transaction(function () use ($user, $asramaId, $studentId) {
            $asrama = Asrama::whereKey($asramaId)->lockForUpdate()->firstOrFail();
            $student = Santri::whereKey($studentId)->lockForUpdate()->firstOrFail();
            if (! $student->is_active) {
                throw ValidationException::withMessages(['studentId' => 'Santri nonaktif tidak dapat ditambahkan.']);
            }
            if ($student->currentPlacement) {
                throw ValidationException::withMessages(['studentId' => 'Santri sudah memiliki penempatan aktif. Lepaskan penempatan sebelumnya atau pindahkan melalui Edit santri.']);
            }
            app(SantriService::class)->save($user, ['name' => $student->name, 'asrama_id' => $asrama->id], $student->id);
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
