<div class="caption mb-5" aria-label="Periode akademik aktif">
    @if($period)<span>Tahun ajaran / semester aktif: <strong>{{ $period->academicYear?->name ?? 'Periode lama' }} · {{ $period->semester ?: $period->name }}</strong></span>@elseif($activeYear)<span>Tahun ajaran aktif: <strong>{{ $activeYear->name }}</strong> · Semester belum aktif.</span>@else<span>Tahun ajaran dan semester aktif belum ditentukan.</span>@endif

</div>
