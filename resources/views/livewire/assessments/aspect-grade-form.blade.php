<div class="space-y-5" x-data="{dirty:false,saved:false}" @change="dirty=true;saved=false" @grades-loaded.window="dirty=false" @grades-saved.window="saved=true" x-on:livewire:navigate.document="if(dirty &amp;&amp; !confirm('Ada nilai yang belum disimpan. Tinggalkan halaman?')) $event.preventDefault()" @beforeunload.window="if(dirty){$event.preventDefault();$event.returnValue=''}">
    <a wire:navigate class="text-link" href="{{ route('santri.rapor', ['asramaId' => $asrama->id]) }}">← Rapor Asrama</a>
    <div class="page-heading"><div><p class="eyebrow">Input nilai per aspek</p><h1>{{ $asrama->name }}</h1><p>{{ $aspect->period->name }} · {{ $aspect->title }}</p></div></div>
    @if($errors->any())<div class="notice notice-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel"><h2>{{ $aspect->title }}</h2><p class="student-prose mt-3">{{ $aspect->indicator ?: 'Indikator belum diisi pada periode ini.' }}</p><p class="caption mt-3">Isi nilai untuk santri pada halaman ini. Mengosongkan nilai akan menghapus nilai aspek ini setelah disimpan. Aspek lain tetap tersimpan.</p></section>
    <form wire:submit="save" class="space-y-4">
    <div class="panel table-wrap"><table><caption class="sr-only">Nilai {{ $aspect->title }} santri {{ $asrama->name }}</caption><thead><tr><th>Santri</th><th>Nilai</th></tr></thead><tbody>
    @forelse($students as $student)
        @php($report = $student->assessments->first())
        @php($item = $report?->items->firstWhere('assessment_aspect_id', $aspect->id))
        <tr wire:key="grade-{{ $student->id }}"><td>{{ $student->name }}<small class="block muted">{{ $student->nisn ?: 'NISN belum diisi' }}</small></td><td>
        @if(array_key_exists($student->id, $versions))
        <select wire:model="ratings.{{ $student->id }}" aria-label="Nilai {{ $student->name }}"><option value="">Belum dinilai</option>@foreach($item?->rating_labels ?? $aspect->period->rating_labels as $label)<option value="{{ $label }}">{{ $label }}</option>@endforeach</select>
        @else<span class="status-badge">{{ $report?->status_label ?? 'Tidak dapat diubah' }}</span><p class="caption">{{ $item?->rating ?: '—' }}</p>@endif
        </td></tr>
    @empty<tr><td colspan="2" class="table-empty">Belum ada santri aktif di asrama ini.</td></tr>@endforelse
    </tbody></table></div>
    <div class="form-actions"><p class="caption" role="status" x-text="dirty ? 'Ada nilai yang belum disimpan.' : (saved ? 'Nilai halaman ini berhasil disimpan.' : 'Nilai disimpan sebagai draft rapor.')"></p><button type="submit" wire:loading.attr="disabled" @disabled(count($versions) === 0)>Simpan nilai halaman ini</button></div>
    </form>
    <nav class="flex justify-between items-center gap-3" aria-label="Halaman input nilai"><button class="button-secondary" wire:click="goToPage({{ $page - 1 }})" @disabled($page <= 1) x-on:click.capture="if(dirty &amp;&amp; !confirm('Nilai belum disimpan. Pindah halaman?')) $event.stopImmediatePropagation()">Sebelumnya</button><span class="caption">{{ $page }} / {{ $students->lastPage() }}</span><button class="button-secondary" wire:click="goToPage({{ $page + 1 }})" @disabled(!$students->hasMorePages()) x-on:click.capture="if(dirty &amp;&amp; !confirm('Nilai belum disimpan. Pindah halaman?')) $event.stopImmediatePropagation()">Berikutnya</button></nav>
</div>
