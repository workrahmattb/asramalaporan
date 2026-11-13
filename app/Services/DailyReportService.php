<?php

namespace App\Services;

use App\Models\DailyReport;
use App\Models\DailyTask;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DailyReportService
{
    /** @param array{report_date:string,status:string,completed?:list<int>,obstacles?:?string,notes?:?string} $data
     * @param list<UploadedFile> $photos */
    public function save(User $user, array $data, array $photos = [], ?DailyReport $report = null): DailyReport
    {
        Gate::forUser($user)->authorize($report ? 'update' : 'create', $report ?: DailyReport::class);
        Validator::make($data, ['report_date' => 'required|date_format:Y-m-d|before_or_equal:today', 'status' => 'required|in:draft,submitted', 'completed' => 'array', 'completed.*' => 'integer|distinct', 'obstacles' => 'nullable|string|max:10000', 'notes' => 'nullable|string|max:10000'])->validate();
        Validator::make(['photos' => $photos], ['photos' => 'array|max:5', 'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120'])->validate();
        if (! $report && ! $user->isAdmin() && $data['report_date'] !== today()->toDateString()) {
            throw ValidationException::withMessages(['report_date' => 'Pelaporan tanggal sebelumnya hanya dapat dibuat admin.']);
        }
        $stored = [];
        try {
            return DB::transaction(function () use ($user, $data, $photos, $report, &$stored) {
                User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if ($report) {
                    $report = DailyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
                    Gate::forUser($user)->authorize('update', $report);
                    $data['report_date'] = $report->report_date->toDateString();
                } else {
                    $asrama = $user->assignedAsramas($data['report_date'])->where('asramas.is_active', true)->first();
                    if (! $asrama) {
                        throw ValidationException::withMessages(['report_date' => 'Belum ada penempatan asrama aktif pada tanggal ini.']);
                    }
                    if (DailyReport::where('user_id', $user->id)->where('asrama_id', $asrama->id)->whereDate('report_date', $data['report_date'])->exists()) {
                        throw ValidationException::withMessages(['report_date' => 'Laporan tanggal ini sudah ada. Buka melalui riwayat.']);
                    }
                    $report = DailyReport::create(['user_id' => $user->id, 'asrama_id' => $asrama->id, 'report_date' => $data['report_date']]);
                    foreach (DailyTask::where('is_active', true)->orderBy('sort_order')->get() as $task) {
                        $report->items()->create(['daily_task_id' => $task->id, 'task_title' => $task->title, 'sort_order' => $task->sort_order]);
                    }
                }
                if ($report->photos()->count() + count($photos) > 5) {
                    throw ValidationException::withMessages(['photos' => 'Maksimal 5 foto per laporan.']);
                }
                $before = $report->toArray();
                $oldItems = $report->items()->pluck('is_completed', 'daily_task_id')->all();
                $valid = $report->items()->pluck('daily_task_id')->all();
                if (array_diff($data['completed'] ?? [], $valid)) {
                    throw ValidationException::withMessages(['completed' => 'Kegiatan tidak valid.']);
                }
                foreach ($report->items as $item) {
                    $item->update(['is_completed' => in_array($item->daily_task_id, $data['completed'] ?? [])]);
                }
                $report->fill(['status' => $data['status'], 'obstacles' => $data['obstacles'] ?? null, 'notes' => $data['notes'] ?? null, 'reviewed_by' => null, 'reviewed_at' => null]);
                $report->submitted_at = $data['status'] === 'submitted' ? ($report->submitted_at ?? now()) : null;
                $report->save();
                foreach ($photos as $photo) {
                    $path = $photo->store('report-photos', 'local');
                    if ($path === false) {
                        throw new \RuntimeException('Foto gagal disimpan.');
                    }
                    $stored[] = $path;
                    $report->photos()->create(['file_path' => $path, 'original_name' => $photo->getClientOriginalName(), 'mime_type' => $photo->getMimeType(), 'file_size' => $photo->getSize()]);
                }
                $this->audit($report, $user, 'saved', ['before' => $before, 'after' => $report->fresh()->toArray(), 'items_before' => $oldItems, 'completed' => $data['completed'] ?? []]);

                return $report->fresh();
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }
    }

    public function review(User $user, DailyReport $report, string $status, string $notes): void
    {
        DB::transaction(function () use ($user, $report, $status, $notes) {
            $report = DailyReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($user)->authorize($status === 'draft' ? 'reopen' : 'review', $report);
            Validator::make(['status' => $status, 'reviewer_notes' => $notes], ['status' => 'in:verified,revision,draft', 'reviewer_notes' => ($status === 'revision' ? 'required' : 'nullable').'|string|max:10000'])->validate();
            $before = $report->status;
            $report->update(['status' => $status, 'reviewer_notes' => $notes, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            $this->audit($report, $user, 'status_changed', ['from' => $before, 'to' => $status, 'notes' => $notes]);
        });
    }

    /** @param array<string,mixed> $changes */
    public function audit(DailyReport $r, User $u, string $action, array $changes): void
    {
        DB::table('report_audits')->insert(['daily_report_id' => $r->id, 'user_id' => $u->id, 'action' => $action, 'changes' => json_encode($changes), 'created_at' => now()]);
    }
}
