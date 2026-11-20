<?php

namespace App\Http\Controllers;

use App\Models\SantriAssessment;
use App\Services\AssessmentPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SantriAssessmentPrintController extends Controller
{
    public function __invoke(SantriAssessment $assessment, AssessmentPdfService $pdf): Response
    {
        Gate::authorize('print', $assessment);

        return $pdf->render('assessments.print', ['report' => $assessment->load('items', 'user', 'reviewer')], 'rapor-santri-'.$assessment->id.'.pdf');
    }
}
