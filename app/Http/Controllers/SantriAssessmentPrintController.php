<?php

namespace App\Http\Controllers;

use App\Models\SantriAssessment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SantriAssessmentPrintController extends Controller
{
    public function __invoke(SantriAssessment $assessment): Response
    {
        Gate::authorize('print', $assessment);

        return response()->view('assessments.print', ['report' => $assessment->load('items', 'user', 'reviewer')])->header('Cache-Control', 'private, no-store');
    }
}
