<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AcademicPeriodService
{
    public function current(): ?AssessmentPeriod
    {
        return AssessmentPeriod::open()->with('academicYear')->latest('id')->first();
    }

    public function deactivate(User $user): void
    {
        Gate::forUser($user)->authorize('manage', Santri::class);
        DB::transaction(function () {
            AssessmentPeriod::orderBy('id')->lockForUpdate()->get();
            AssessmentPeriod::where('is_active', true)->update(['is_active' => false]);
        }, 3);
    }

    public function activate(User $user, int $yearId, int $periodId): void
    {
        Gate::forUser($user)->authorize('manage', Santri::class);
        DB::transaction(function () use ($yearId, $periodId) {
            AcademicYear::orderBy('id')->lockForUpdate()->get();
            AssessmentPeriod::orderBy('id')->lockForUpdate()->get();
            $year = AcademicYear::findOrFail($yearId);
            $period = AssessmentPeriod::findOrFail($periodId);
            if ($period->academic_year_id !== $year->id) {
                throw ValidationException::withMessages(['periodId' => 'Semester harus berasal dari tahun ajaran yang dipilih.']);
            }
            if (! $period->aspects()->exists()) {
                throw ValidationException::withMessages(['periodId' => 'Lengkapi aspek semester terlebih dahulu sebelum diaktifkan.']);
            }
            AcademicYear::where('id', '!=', $year->id)->update(['is_active' => false]);
            $year->update(['is_active' => true]);
            AssessmentPeriod::where('id', '!=', $period->id)->update(['is_active' => false]);
            $period->update(['is_active' => true]);
        }, 3);
    }
}
