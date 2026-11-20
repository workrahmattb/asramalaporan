<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>Rapor {{ $asrama->name }}</title>
@include('assessments.print-styles')
</head><body>@forelse($reports as $report)<main class="sheet">@include('assessments.sheet', ['report' => $report])</main>@empty<main class="sheet"><h1>Belum ada rapor tersimpan</h1><p>{{ $asrama->name }} · {{ $period->name }}</p></main>@endforelse</body></html>
