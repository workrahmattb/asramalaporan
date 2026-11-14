<?php

namespace App\Services;

use App\Models\Asrama;
use App\Models\AssessmentAspect;
use App\Models\Santri;
use App\Models\SantriAssessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AspectGradeService
{
    public function authorizeContext(User $user, Asrama $asrama, AssessmentAspect $aspect): void
    {
        $user = User::findOrFail($user->id);
        $asrama = Asrama::findOrFail($asrama->id);
        $aspect = AssessmentAspect::with('period.academicYear')->findOrFail($aspect->id);
        abort_unless($user->is_active && ($user->isAdmin() || $user->isPembina()), 403);
        abort_unless($asrama->is_active && ($user->isAdmin() || $user->assignedAsramas()->where('asramas.id', $asrama->id)->exists()), 403);
        abort_unless($aspect->period->isOpen(), 403);
    }

    /** @param array<int,array{rating:string,version:?int}> $rows */
    public function save(User $user, Asrama $asrama, AssessmentAspect $aspect, array $rows): void
    {
        $this->authorizeContext($user, $asrama, $aspect);
        validator(['rows' => $rows], ['rows' => 'required|array|max:10', 'rows.*.rating' => 'nullable|string|max:100', 'rows.*.version' => 'nullable|integer'])->validate();
        DB::transaction(function () use ($user, $asrama, $aspect, $rows) {
            ksort($rows);
            foreach ($rows as $id => $row) {
                $student = Santri::whereKey($id)->lockForUpdate()->firstOrFail();
                Gate::forUser($user)->authorize('view', $student);
                abort_unless($student->is_active && $student->currentPlacement?->asrama_id === $asrama->id, 403);
                $this->authorizeContext($user, $asrama->fresh(), $aspect->fresh());
                $report = SantriAssessment::where('santri_id', $id)->where('assessment_period_id', $aspect->assessment_period_id)->with('items')->first();
                if ($report) {
                    Gate::forUser($user)->authorize('update', $report);
                    if (! $report->items->contains('assessment_aspect_id', $aspect->id)) {
                        throw ValidationException::withMessages(['ratings' => 'Aspek berbeda dari snapshot laporan. Hubungi admin.']);
                    }
                }
                if (! $report && $row['rating'] === '') {
                    if ($row['version'] !== null) {
                        throw ValidationException::withMessages(['ratings' => 'Data berubah. Muat ulang halaman.']);
                    }

                    continue;
                }
                $ratings = $report ? $report->items->mapWithKeys(fn ($item) => [$item->assessment_aspect_id => $item->rating ?? ''])->all() : [];
                $ratings[$aspect->id] = $row['rating'];
                app(SantriAssessmentService::class)->save($user, $student, $aspect->period, [
                    'status' => 'draft',
                    'ratings' => $ratings,
                    'item_notes' => $report ? $report->items->mapWithKeys(fn ($item) => [$item->assessment_aspect_id => $item->notes])->all() : [],
                    'development' => $report?->development,
                    'next_focus' => $report?->next_focus,
                ], $row['version']);
            }
        }, 3);
    }
}
