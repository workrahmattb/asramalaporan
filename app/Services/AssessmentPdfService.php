<?php

namespace App\Services;

use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;

class AssessmentPdfService
{
    /** @param array<string,mixed> $data */
    public function render(string $view, array $data, string $filename): Response
    {
        /** @var PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $pdf->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans'])
            ->loadView($view, $data)->setPaper('a4', 'portrait');

        return new Response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
