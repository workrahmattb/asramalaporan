<?php

namespace App\Http\Controllers;

use App\Models\Asrama;
use App\Models\AssessmentAspect;
use App\Models\AssessmentPeriod;
use App\Models\Santri;
use App\Models\SantriAssessment;
use App\Services\XlsxExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class AssessmentExportController extends Controller
{
    private function authorizeAsrama(Request $request, Asrama $asrama): void
    {
        $user = $request->user();
        abort_unless($user->is_active && ($user->isAdmin() || $user->isPembina() || $user->role === 'koordinator'), 403);
        abort_unless($user->isAdmin() || $user->assignedAsramas()->where('asramas.id', $asrama->id)->exists(), 403);
    }

    public function leger(Request $request, Asrama $asrama, AssessmentPeriod $period, XlsxExportService $xlsx): BinaryFileResponse
    {
        $this->authorizeAsrama($request, $asrama);
        $reports = SantriAssessment::visibleTo($request->user())->where('asrama_id', $asrama->id)->where('assessment_period_id', $period->id)->with('items')->get()->keyBy('santri_id');
        $students = Santri::visibleTo($request->user())->where('is_active', true)->whereHas('currentPlacement', fn ($q) => $q->where('asrama_id', $asrama->id))->orderBy('name')->get()->keyBy('id');
        $aspects = $period->aspects;
        $rows = [array_merge(['Nama', 'NISN', 'Asrama', 'Periode', 'Status'], array_values($aspects->map(fn (AssessmentAspect $aspect): string => $aspect->title)->all()), ['Perkembangan', 'Fokus pembinaan'])];
        foreach ($students->keys()->merge($reports->keys())->unique() as $id) {
            $report = $reports->get($id);
            $student = $students->get($id);
            $values = [];
            foreach ($aspects as $aspect) {
                $values[] = $report?->items->firstWhere('assessment_aspect_id', $aspect->id)->rating ?? '';
            }
            $rows[] = array_merge([$report->student_name ?? $student->name ?? '', $report ? ($report->student_nisn ?? '') : ($student->nisn ?? ''), $report->asrama_name ?? $asrama->name, $report->period_name ?? $period->name, $report->status_label ?? 'Belum dinilai'], $values, [$report->development ?? '', $report->next_focus ?? '']);
        }

        return $xlsx->download($rows, 'leger-asrama-'.$asrama->id.'-periode-'.$period->id.'.xlsx');
    }

    public function printAsrama(Request $request, Asrama $asrama, AssessmentPeriod $period): Response
    {
        $this->authorizeAsrama($request, $asrama);
        $reports = SantriAssessment::visibleTo($request->user())->where('asrama_id', $asrama->id)->where('assessment_period_id', $period->id)->where('status', 'approved')->with('items', 'user', 'reviewer')->orderBy('student_name')->get();

        return response()->view('assessments.print-asrama', compact('reports', 'asrama', 'period'))->header('Cache-Control', 'private, no-store');
    }
}
