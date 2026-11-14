<div class="space-y-5">
    <a wire:navigate class="text-link" href="{{ route('santri.index') }}">← Daftar santri</a>
    <div class="page-heading"><div><p class="eyebrow">Pembinaan santri</p><h1>Rapor Asrama</h1><p>Isi nilai santri sesuai mata pelajaran atau aspek yang ditentukan admin.</p></div></div>
    @if($period)
        <section class="panel"><p class="eyebrow">Periode aktif</p><h2>{{ $period->name }}</h2><p class="caption mt-2">Periode dan komponen penilaian ditentukan oleh admin.</p><details class="mt-4"><summary>Mata pelajaran / aspek yang dinilai ({{ $period->aspects->count() }})</summary><ul class="mt-3 space-y-2">@foreach($period->aspects as $aspect)<li>{{ $aspect->title }}</li>@endforeach</ul></details></section>
        <section class="panel filter-grid" aria-label="Filter rapor asrama">
            <label>Cari santri<input type="search" wire:model.live.debounce.300ms="search" placeholder="Nama atau NISN"></label>
            <label>Asrama<select wire:model.live="asramaId"><option value="">Semua asrama</option>@foreach($asramas as $asrama)<option value="{{ $asrama->id }}">{{ $asrama->name }}</option>@endforeach</select></label>
            <label>Aspek penilaian<select wire:model.live="aspectId"><option value="">Pilih aspek untuk diisi</option>@foreach($period->aspects as $aspect)<option value="{{ $aspect->id }}">{{ $aspect->title }}</option>@endforeach</select></label>
            <label>Kelengkapan<select wire:model.live="progress"><option value="">Semua santri</option><option value="incomplete">Belum tuntas dinilai</option></select></label>
        </section>
        @if($selectedAsrama && $selectedAspect && (auth()->user()->isPembina() || auth()->user()->isAdmin()))
        <section class="panel"><h2>{{ $selectedAspect->title }}</h2><p class="student-prose caption mt-3">{{ $selectedAspect->indicator ?: 'Indikator belum diisi.' }}</p><a wire:navigate class="btn mt-4" href="{{ route('santri.grades', [$selectedAsrama, $selectedAspect]) }}">Isi nilai seluruh santri</a></section>
        @else<p class="caption">Pilih asrama dan aspek di atas untuk mengisi nilai seluruh santri dalam tabel.</p>@endif
        <div class="panel-heading"><h2>Kelengkapan & rapor santri</h2></div>
        @if($selectedAsrama)<div class="flex flex-wrap gap-3"><a class="btn button-secondary" href="{{ route('santri.leger', [$selectedAsrama, $period]) }}">Unduh leger Excel</a><a class="btn button-secondary" href="{{ route('santri.print-asrama', [$selectedAsrama, $period]) }}" target="_blank" rel="noopener">Cetak rapor satu asrama</a></div>@endif
        <div class="panel table-wrap"><table><caption class="sr-only">Rapor santri pada periode aktif</caption><thead><tr><th>Santri / asrama</th><th>Status</th><th>Penilaian</th></tr></thead><tbody>
        @forelse($students as $student)
            @php($assessment = $student->assessments->first())
            <tr wire:key="rapor-student-{{ $student->id }}"><td>{{ $student->name }}<small class="block muted">{{ $student->currentPlacement?->asrama?->name ?? 'Belum ditempatkan' }}</small></td><td><span class="status-badge status-{{ $assessment?->status ?? 'draft' }}">{{ $assessment?->status_label ?? 'Belum dinilai' }}</span>@if($assessment)<small class="block muted">{{ $assessment->items->whereNotNull('rating')->count() }}/{{ $assessment->items->count() }} aspek terisi</small>@endif</td><td>
            @if($assessment)
                @can('update', $assessment)<a wire:navigate class="text-link" href="{{ route('assessments.form', [$student, $period]) }}">Catatan & kirim rapor</a>@endcan
                <a wire:navigate class="text-link" href="{{ route('assessments.show', $assessment) }}">Lihat rapor</a>
            @else<span class="caption">Isi nilai melalui aspek di atas.</span>@endif
            </td></tr>
        @empty<tr><td colspan="3" class="table-empty">Belum ada santri sesuai asrama atau pencarian ini.</td></tr>@endforelse
        </tbody></table></div>
        {{ $students->links() }}
    @else
        <section class="panel empty-state"><h2>Belum ada periode aktif</h2><p>Admin belum mengaktifkan periode rapor. Kamu tetap bisa melihat identitas santri melalui menu Santri.</p></section>
    @endif
</div>
