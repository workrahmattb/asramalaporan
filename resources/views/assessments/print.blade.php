<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Rapor {{ $report->student_name }}</title>
@include('assessments.print-styles')
</head><body><main class="sheet">@include('assessments.sheet', ['report' => $report])</main></body></html>
