<div class="space-y-5">
    <div class="page-heading"><div><p class="eyebrow">Pembinaan santri</p><h1>{{ auth()->user()->isPembina() ? 'Santri binaanku' : 'Data santri' }}</h1><p>Identitas santri sesuai penempatan asrama.</p></div>@can('manage', \App\Models\Santri::class)<button wire:click="create"><x-simus-icon name="plus"/>Tambah santri</button>@endcan</div>
    <section class="panel filter-grid" aria-label="Filter santri">
        <label>Cari santri<input type="search" wire:model.live.debounce.300ms="search" placeholder="Nama atau NISN"></label>
        <label>Asrama<select wire:model.live="asramaId"><option value="">{{ auth()->user()->isPembina() ? 'Semua asramaku' : 'Semua asrama' }}</option>@foreach($asramas as $asrama)<option value="{{ $asrama->id }}">{{ $asrama->name }}</option>@endforeach</select></label>
        @if(!auth()->user()->isPembina())
        <label>Periode penilaian<select wire:model.live="periodId"><option value="">Pilih periode</option>@foreach($periods as $period)<option value="{{ $period->id }}">{{ $period->name }}</option>@endforeach</select></label>
        <label>Kelengkapan<select wire:model.live="progress"><option value="">Semua santri</option><option value="incomplete">Belum tuntas dinilai</option></select></label>
        @endif
        @can('manage', \App\Models\Santri::class)<label>Status santri<select wire:model.live="active"><option value="1">Aktif</option><option value="0">Nonaktif</option><option value="">Semua</option></select></label>@endcan
    </section>
    <div class="panel-heading"><div><h2>Daftar santri</h2><p class="caption">{{ $santris->total() }} santri sesuai filter</p></div><a wire:navigate class="btn" href="{{ route('santri.rapor', array_filter(['asramaId' => $asramaId])) }}"><x-simus-icon name="report"/>Rapor Asrama</a></div>
    <p class="caption" role="status" wire:loading>Memperbarui daftar santri…</p>
    <div class="panel table-wrap"><table><caption class="sr-only">Identitas santri dan orang tua sesuai asrama</caption><thead><tr><th>Nama / NISN</th><th>Asrama</th><th>Tempat, tanggal lahir</th><th>Nama ayah</th><th>Nama ibu</th>@can('manage', \App\Models\Santri::class)<th>Aksi</th>@endcan</tr></thead><tbody>
    @forelse($santris as $student)
        <tr wire:key="santri-{{ $student->id }}"><td><a wire:navigate class="text-link" href="{{ route('santri.show', $student) }}">{{ $student->name }}</a><small class="block muted">{{ $student->nisn ?: 'NISN belum diisi' }}</small></td><td>{{ $student->currentPlacement?->asrama?->name ?? 'Belum ditempatkan' }}</td><td>{{ $student->birth_place ?: '—' }}{{ $student->birth_date ? ', '.$student->birth_date->format('d/m/Y') : '' }}</td><td>{{ $student->father_name ?: '—' }}</td><td>{{ $student->mother_name ?: '—' }}</td>@can('manage', \App\Models\Santri::class)<td><button class="button-secondary" wire:click="edit({{ $student->id }})">Edit</button></td>@endcan</tr>
    @empty<tr><td colspan="{{ auth()->user()->isAdmin() ? 6 : 5 }}" class="table-empty">Belum ada santri sesuai asrama atau pencarian ini.</td></tr>@endforelse
    </tbody></table></div>
    {{ $santris->links() }}
@can('manage',\App\Models\Santri::class)<x-admin-data-modal name="santri-editor" :title="$editId?'Edit santri':'Tambah santri'" description="Hanya nama yang wajib diisi. Data lainnya dapat dilengkapi kemudian." :submit-label="$editId?'Simpan perubahan':'Simpan santri'">
<label>Nama lengkap<input type="text" wire:model="name" required autofocus>@error('name')<span class="field-error">{{ $message }}</span>@enderror</label>
<label>NISN (opsional)<input type="text" wire:model="nisn">@error('nisn')<span class="field-error">{{ $message }}</span>@enderror</label>
<label>Tempat lahir (opsional)<input type="text" wire:model="birth_place">@error('birth_place')<span class="field-error">{{ $message }}</span>@enderror</label>
<label>Tanggal lahir (opsional)<input type="date" wire:model="birth_date" max="{{ today()->toDateString() }}">@error('birth_date')<span class="field-error">{{ $message }}</span>@enderror</label>
<label>Nama ayah (opsional)<input type="text" wire:model="father_name">@error('father_name')<span class="field-error">{{ $message }}</span>@enderror</label>
<label>Nomor kontak ayah (opsional)<input type="tel" wire:model="father_phone">@error('father_phone')<span class="field-error">{{ $message }}</span>@enderror</label>
<label>Nama ibu (opsional)<input type="text" wire:model="mother_name">@error('mother_name')<span class="field-error">{{ $message }}</span>@enderror</label>
<label>Nomor kontak ibu (opsional)<input type="tel" wire:model="mother_phone">@error('mother_phone')<span class="field-error">{{ $message }}</span>@enderror</label>
<label>Kategori (opsional)<select wire:model="gender"><option value="">Belum diisi</option><option value="putra">Putra</option><option value="putri">Putri</option></select>@error('gender')<span class="field-error">{{ $message }}</span>@enderror</label>
<label>Asrama (opsional)<select wire:model="asrama_id"><option value="">Belum ditempatkan</option>@foreach($asramas as $a)<option value="{{ $a->id }}">{{ $a->name }} {{ $a->gender?'('.$a->gender.')':'' }}</option>@endforeach</select>@error('asrama_id')<span class="field-error">{{ $message }}</span>@enderror</label>
<p class="caption form-wide">Santri yang belum ditempatkan hanya terlihat oleh admin. Pilih asrama sebelum pembina mengisi catatan dan penilaian. Jika kategori kosong, kategori mengikuti asrama yang dipilih.</p>
<label class="flex gap-3 items-center"><input type="checkbox" wire:model="is_active">Santri aktif</label></x-admin-data-modal>@endcan
</div>