<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentSubject;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AcademicYearService
{
    /** @param array{name:string,start_date:string,end_date:string} $data */
    public function save(User $user, array $data, ?int $id = null): AcademicYear
    {
        Gate::forUser($user)->authorize('manage', Santri::class);
        validator($data, ['name' => 'required|string|max:255|unique:academic_years,name,'.($id ?? 'NULL'), 'start_date' => 'required|date_format:Y-m-d', 'end_date' => 'required|date_format:Y-m-d|after:start_date'])->validate();

        return DB::transaction(function () use ($data, $id) {
            $year = $id ? AcademicYear::whereKey($id)->lockForUpdate()->firstOrFail() : new AcademicYear;
            $year->fill($data)->save();
            $start = Carbon::parse($data['start_date']);
            $end = Carbon::parse($data['end_date']);
            $middle = $start->copy()->addMonthsNoOverflow(6)->min($end->copy()->subDay());
            foreach (['Ganjil', 'Genap'] as $semester) {
                $period = AssessmentPeriod::where('academic_year_id', $year->id)->where('semester', $semester)->lockForUpdate()->first();
                if (! $period) {
                    $period = AssessmentPeriod::create(['academic_year_id' => $year->id, 'semester' => $semester, 'name' => 'Semester '.$semester.' '.$year->name, 'is_active' => false, 'start_date' => ($semester === 'Ganjil' ? $start : $middle->copy()->addDay())->toDateString(), 'end_date' => ($semester === 'Ganjil' ? $middle : $end)->toDateString(), 'rating_labels' => ['A', 'B', 'C', 'D']]);
                    foreach (AssessmentSubject::where('is_active', true)->orderBy('title')->get() as $i => $subject) {
                        $period->aspects()->create(['assessment_subject_id' => $subject->id, 'title' => $subject->title, 'indicator' => $subject->indicator, 'sort_order' => $i + 1]);
                    }
                } elseif (! $period->assessments()->exists()) {
                    $period->update(['name' => 'Semester '.$semester.' '.$year->name, 'start_date' => ($semester === 'Ganjil' ? $start : $middle->copy()->addDay())->toDateString(), 'end_date' => ($semester === 'Ganjil' ? $middle : $end)->toDateString()]);
                }
            }

            return $year;
        }, 3);
    }

    public function toggleYear(User $user, int $id): void
    {
        Gate::forUser($user)->authorize('manage', Santri::class);
        DB::transaction(function () use ($id) {
            AcademicYear::orderBy('id')->lockForUpdate()->get();
            AssessmentPeriod::orderBy('id')->lockForUpdate()->get();
            $year = AcademicYear::findOrFail($id);
            $activate = ! $year->is_active;
            if ($activate) {
                AcademicYear::where('id', '!=', $year->id)->update(['is_active' => false]);
                AssessmentPeriod::where('is_active', true)->update(['is_active' => false]);
            } else {
                AssessmentPeriod::where('academic_year_id', $year->id)->update(['is_active' => false]);
            }
            $year->update(['is_active' => $activate]);
        }, 3);
    }

    public function toggleSemester(User $user, int $id): void
    {
        Gate::forUser($user)->authorize('manage', Santri::class);
        $period = AssessmentPeriod::with('academicYear')->findOrFail($id);
        if (! $period->academicYear?->is_active) {
            throw ValidationException::withMessages(['year' => 'Aktifkan tahun ajaran ini terlebih dahulu.']);
        }
        if ($period->is_active) {
            app(AcademicPeriodService::class)->deactivate($user);
        } else {
            app(AcademicPeriodService::class)->activate($user, $period->academic_year_id, $period->id);
        }
    }

    public function delete(User $user, int $id): void
    {
        Gate::forUser($user)->authorize('manage', Santri::class);
        DB::transaction(function () use ($id) {
            $year = AcademicYear::whereKey($id)->lockForUpdate()->firstOrFail();
            $periods = AssessmentPeriod::where('academic_year_id', $year->id)->lockForUpdate()->get();
            if ($periods->contains(fn ($period) => $period->assessments()->exists())) {
                throw ValidationException::withMessages(['year' => 'Tahun ajaran memiliki nilai/rapor. Nonaktifkan untuk menyimpan riwayatnya.']);
            }
            foreach ($periods as $period) {
                $period->aspects()->delete();
                $period->delete();
            }
            $year->delete();
        }, 3);
    }

    /** @return array{deleted_years:int,deleted_periods:int,archived_years:int} */
    public function resetActive(User $user): array
    {
        Gate::forUser($user)->authorize('manage', Santri::class);

        return DB::transaction(function () use ($user) {
            $result = ['deleted_years' => 0, 'deleted_periods' => 0, 'archived_years' => 0];
            foreach (AcademicYear::where('is_active', true)->lockForUpdate()->get() as $year) {
                $used = AssessmentPeriod::where('academic_year_id', $year->id)->whereHas('assessments')->exists();
                if ($used) {
                    $year->update(['is_active' => false]);
                    $result['archived_years']++;
                } else {
                    $this->delete($user, $year->id);
                    $result['deleted_years']++;
                }
            }
            foreach (AssessmentPeriod::where('is_active', true)->lockForUpdate()->get() as $period) {
                if (! $period->academic_year_id && ! $period->assessments()->exists()) {
                    $period->aspects()->delete();
                    $period->delete();
                    $result['deleted_periods']++;
                } else {
                    $period->update(['is_active' => false]);
                }
            }

            return $result;
        }, 3);
    }
}
