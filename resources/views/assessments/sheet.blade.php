@php
    $dormGender = $report->relationLoaded('asrama') ? $report->asrama?->gender : null;
    $institution = config('rapor.institutions.'.$dormGender, []);
    $institutionName = $institution['name'] ?? "Pondok Pesantren Syafa'aturrasul";
    $institutionAddress = $institution['address'] ?? 'Kuantan Singingi, Riau';
    $logo = $institution['logo'] ?? null;
    $logoData = $logo && is_file(public_path($logo)) ? 'data:image/png;base64,'.base64_encode(file_get_contents(public_path($logo))) : null;
@endphp
<div class="institution-heading">
    @if($logoData)<div class="institution-logo"><img src="{{ $logoData }}" alt="Logo {{ $institutionName }}"></div>@endif
    <div class="institution-name">{{ $institutionName }}</div>
    <div class="institution-address">{{ $institutionAddress }}</div>
</div>
<header class="report-heading">
    <h1>LAPORAN PENILAIAN SIKAP KEPRIBADIAN DAN SOSIAL SANTRI</h1>
    <p>ASSESSMENT REPORT OF STUDENTS’ RELIGIOUS AND SOCIAL BEHAVIOR</p>
</header>
<table class="identity">
    <tr><td>Nama Santri <em>(student’s name)</em></td><td class="colon">:</td><td><strong>{{ $report->student_name }}</strong></td></tr>
    <tr><td>Asrama <em>(dormitory)</em></td><td class="colon">:</td><td><strong>{{ $report->asrama_name }}</strong></td></tr>
    <tr><td>Tahun Pelajaran <em>(Academic Year)</em></td><td class="colon">:</td><td>{{ $report->period_name }}</td></tr>
    <tr><td>NISN</td><td class="colon">:</td><td>{{ $report->student_nisn ?: '—' }}</td></tr>
</table>
<h2>A. Penilaian Sikap <em>(Behavior Assessment)</em></h2>
<table class="grades">
    <thead><tr><th class="number">No</th><th class="aspect">Aspek Penilaian<br><em>(Assessment Aspect)</em></th><th>Indikator <em>(Indicator)</em></th><th class="rating">NILAI</th></tr></thead>
    <tbody>@foreach($report->items as $item)<tr><td class="center">{{ $loop->iteration }}</td><td><strong>{{ $item->aspect_title }}</strong></td><td>{{ $item->indicator ?: '—' }}@if($item->notes)<div class="item-note">Catatan: {{ $item->notes }}</div>@endif</td><td class="center">{{ $item->rating ?: '—' }}</td></tr>@endforeach</tbody>
</table>
<section class="comments"><h3>CATATAN MUSYRIF / MUSYRIFAH</h3><div class="comments-content"><div class="prose">{{ $report->development ?: '—' }}</div>@if($report->next_focus)<div class="focus"><strong>Fokus pembinaan:</strong> {{ $report->next_focus }}</div>@endif</div></section>
<div class="signature-section">
    <p class="report-date">{{ config('rapor.city') ? config('rapor.city').', ' : '' }}{{ ($report->reviewed_at ?? now())->locale('id')->translatedFormat('d F Y') }}</p>
    <table class="signatures"><tr><td><div class="sign-space"></div><strong class="signature-name">{{ $report->user->name }}</strong><br>Pembina Asrama</td><td><div class="sign-space"></div><span class="signature-line">&nbsp;</span><br>Walisantri</td></tr></table>
    <p class="acknowledged">Diketahui Oleh</p>
    <table class="signatures"><tr><td><div class="sign-space"></div>@if(config('rapor.head_of_care'))<strong class="signature-name">{{ config('rapor.head_of_care') }}</strong>@else<span class="signature-line">&nbsp;</span>@endif<br>Kepala Pengasuhan Santri</td><td><div class="sign-space"></div>@if(config('rapor.director'))<strong class="signature-name">{{ config('rapor.director') }}</strong>@else<span class="signature-line">&nbsp;</span>@endif<br>Pimpinan Pondok</td></tr></table>
</div>
