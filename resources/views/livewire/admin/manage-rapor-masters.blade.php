<div class="space-y-5">
    <section class="panel"><div class="panel-heading"><div><h2>Master aspek & indikator</h2><p class="caption">Dipilih saat membuat semester. Perubahan master berlaku untuk semester baru.</p></div><button wire:click="create('subject')">Tambah aspek</button></div>
    @forelse($subjects as $subject)<div class="list-row" wire:key="subject-{{ $subject->id }}"><div><strong>{{ $subject->title }}</strong><p class="student-prose caption">{{ $subject->indicator ?: 'Indikator belum diisi' }}</p><small>{{ $subject->is_active ? 'Aktif' : 'Tidak aktif' }}</small></div><button class="button-secondary" wire:click="edit('subject', {{ $subject->id }})">Edit</button></div>@empty<p class="caption">Tambahkan aspek seperti Ibadah, Adab, Tahfiz, atau Fikih beserta indikatornya.</p>@endforelse
    {{ $subjects->links() }}</section>
    <x-admin-data-modal name="rapor-master-editor" :title="($editId ? 'Edit ' : 'Tambah ').($kind === 'year' ? 'tahun ajaran' : 'aspek penilaian')" description="Data master tidak mengubah nilai dan snapshot rapor yang sudah tersimpan." submit-label="Simpan">
    @if($errors->any())<div role="alert" class="notice notice-error form-wide"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <label class="form-wide">{{ $kind === 'year' ? 'Tahun ajaran' : 'Nama aspek' }}<input wire:model="title" required autofocus placeholder="{{ $kind === 'year' ? '2026/2027' : 'Contoh: Adab' }}"></label>
    @if($kind === 'subject')<label class="form-wide">Indikator penilaian<textarea wire:model="indicator" rows="4" placeholder="Perilaku atau capaian yang menjadi dasar penilaian."></textarea></label>@endif
    @if(!$centralized || $kind === 'subject')<label class="flex gap-3 items-center form-wide"><input type="checkbox" wire:model="is_active">Aktif</label>@else<p class="caption form-wide">Terapkan tahun ajaran dan semester aktif melalui pengaturan bersama di atas.</p>@endif
    </x-admin-data-modal>
</div>
