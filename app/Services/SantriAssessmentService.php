<?php

namespace App\Services;

use App\Models\AssessmentPeriod;
use App\Models\Santri;
use App\Models\SantriAssessment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SantriAssessmentService
{
    /** @param array<string,mixed> $data */
    public function save(User $user, Santri $santri, AssessmentPeriod $period, array $data, ?int $version = null): SantriAssessment
    {
        Gate::forUser($user)->authorize('view', $santri);
        abort_unless($user->isAdmin() || $user->isPembina(), 403);
        Validator::make($data, ['status' => 'required|in:draft,submitted', 'ratings' => 'present|array', 'ratings.*' => 'nullable|string|max:100', 'item_notes' => 'array', 'item_notes.*' => 'nullable|string|max:2000', 'development' => (($data['status'] ?? null) === 'submitted' ? 'required' : 'nullable').'|string|max:10000', 'next_focus' => (($data['status'] ?? null) === 'submitted' ? 'required' : 'nullable').'|string|max:10000'])->validate();

        return DB::transaction(function () use ($user, $santri, $period, $data, $version) {
            $santri = Santri::whereKey($santri->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('view', $santri);
            $period = AssessmentPeriod::whereKey($period->id)->lockForUpdate()->firstOrFail();
            $report = SantriAssessment::where('santri_id', $santri->id)->where('assessment_period_id', $period->id)->lockForUpdate()->first();
            if ($report) {
                Gate::forUser($user)->authorize('update', $report);
                if ($version !== $report->version) {
                    throw ValidationException::withMessages(['ratings' => 'Laporan berubah sejak dibuka. Muat ulang agar perubahan pembina lain tidak tertimpa.']);
                }
            } else {
                if ($version !== null) {
                    throw ValidationException::withMessages(['ratings' => 'Laporan tidak tersedia. Muat ulang halaman.']);
                }
                if (! $period->isOpen()) {
                    throw ValidationException::withMessages(['ratings' => 'Periode penilaian belum dibuka atau sudah ditutup.']);
                }
                if (! $santri->is_active) {
                    throw ValidationException::withMessages(['ratings' => 'Santri nonaktif tidak dapat dinilai.']);
                }
                $placement = $santri->currentPlacement;
                if (! $placement || ! $placement->asrama->is_active) {
                    throw ValidationException::withMessages(['ratings' => 'Penempatan asrama aktif belum tersedia.']);
                }
                if (! $period->aspects()->exists()) {
                    throw ValidationException::withMessages(['ratings' => 'Admin belum mengatur aspek penilaian.']);
                }
                $report = SantriAssessment::create(['santri_id' => $santri->id, 'assessment_period_id' => $period->id, 'asrama_id' => $placement->asrama_id, 'user_id' => $user->id, 'student_name' => $santri->name, 'student_nisn' => $santri->nisn, 'asrama_name' => $placement->asrama->name, 'period_name' => $period->name]);
                foreach ($period->aspects as $aspect) {
                    $report->items()->create(['assessment_aspect_id' => $aspect->id, 'aspect_title' => $aspect->title, 'indicator' => $aspect->indicator, 'sort_order' => $aspect->sort_order, 'rating_labels' => $period->rating_labels]);
                }
            }
            $before = $report->load('items')->toArray();
            $validIds = $report->items->pluck('assessment_aspect_id')->map(fn ($id) => (string) $id)->all();
            if (array_diff(array_keys($data['ratings']), $validIds) || array_diff(array_keys($data['item_notes'] ?? []), $validIds)) {
                throw ValidationException::withMessages(['ratings' => 'Aspek penilaian tidak valid.']);
            }
            foreach ($report->items as $item) {
                $rating = $data['ratings'][$item->assessment_aspect_id] ?? null;
                if ($rating === '') {
                    $rating = null;
                }
                if (($rating !== null && ! in_array($rating, $item->rating_labels, true)) || ($data['status'] === 'submitted' && $rating === null)) {
                    throw ValidationException::withMessages(['ratings' => 'Pilih nilai yang tersedia untuk seluruh aspek sebelum mengirim.']);
                }
                $item->update(['rating' => $rating, 'notes' => $data['item_notes'][$item->assessment_aspect_id] ?? null]);
            }
            $report->fill(['status' => $data['status'], 'development' => $data['development'] ?? null, 'next_focus' => $data['next_focus'] ?? null, 'reviewed_at' => null, 'reviewed_by' => null, 'version' => $report->version + 1]);
            $report->submitted_at = $data['status'] === 'submitted' ? now() : null;
            $report->save();
            $this->audit($user, $report, 'saved', ['before' => $before, 'after' => $report->fresh()->load('items')->toArray()]);

            return $report->fresh();
        });
    }

    public function review(User $user, SantriAssessment $assessment, string $status, string $notes): void
    {
        Validator::make(['status' => $status, 'reviewer_notes' => $notes], ['status' => 'required|in:approved,revision', 'reviewer_notes' => ($status === 'revision' ? 'required' : 'nullable').'|string|max:10000'])->validate();
        DB::transaction(function () use ($user, $assessment, $status, $notes) {
            $report = SantriAssessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('review', $report);
            $before = $report->status;
            $report->update(['status' => $status, 'reviewer_notes' => $notes, 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'version' => $report->version + 1]);
            $this->audit($user, $report, 'reviewed', ['from' => $before, 'to' => $status, 'notes' => $notes]);
        });
    }

    public function reopen(User $user, SantriAssessment $assessment, string $notes): void
    {
        Validator::make(['reviewer_notes' => $notes], ['reviewer_notes' => 'required|string|max:10000'])->validate();
        DB::transaction(function () use ($user, $assessment, $notes) {
            $report = SantriAssessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize('reopen', $report);
            $report->update(['status' => 'revision', 'reviewer_notes' => $notes, 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'version' => $report->version + 1]);
            $this->audit($user, $report, 'reopened', ['notes' => $notes]);
        });
    }

    /** @param array<string,mixed> $changes */
    private function audit(User $user, SantriAssessment $report, string $action, array $changes): void
    {
        DB::table('assessment_audits')->insert(['santri_assessment_id' => $report->id, 'user_id' => $user->id, 'action' => $action, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
