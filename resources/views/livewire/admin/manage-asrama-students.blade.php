<div class="space-y-5">
    <a wire:navigate class="text-link" href="{{ route('admin.asramas') }}">← Daftar asrama</a>
    <div class="page-heading"><div><p class="eyebrow">Kelola santri asrama</p><h1>{{ $asrama->name }}</h1><p>Tambahkan santri yang sudah terdaftar ke asrama ini.</p></div></div>
    @if(!$asrama->is_active)<p class="notice">Asrama nonaktif. Aktifkan asrama sebelum menambahkan santri.</p>@endif
    @if($notice)<p class="notice notice-success" role="status">{{ $notice }}</p>@endif
    @if($errors->any())<div class="notice notice-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel space-y-4">
        <h2>Tambah Santri ke Asrama</h2>
        <form wire:submit="save" class="space-y-4">
            <div class="asrama-student-picker" @click.outside="$wire.set('showDropdown', false)" @keydown.escape.window="$wire.set('showDropdown', false)">
                @if($selectedStudent)
                    <p class="caption">Santri dipilih</p><div class="asrama-selected-student"><div><strong>{{ $selectedStudent->name }}</strong><small class="block muted">NISN {{ $selectedStudent->nisn ?: 'belum diisi' }}</small></div><button type="button" class="button-secondary" wire:click="clearSelection" aria-label="Batal memilih santri">Batal pilih</button></div>
                @else
                    <label for="asrama-student-search">Pilih santri<input id="asrama-student-search" type="search" wire:model.live.debounce.300ms="candidateSearch" wire:focus="openCandidates" wire:click="openCandidates" placeholder="Klik untuk melihat santri atau cari nama/NISN…" autocomplete="off" aria-expanded="{{ $showDropdown ? 'true' : 'false' }}" aria-controls="asrama-student-options"></label>
                @endif
                @if($showDropdown)
                    <div id="asrama-student-options" class="asrama-student-options" aria-label="Santri belum ditempatkan">
                        <label class="sr-only" for="asrama-student-options-search">Cari santri belum ditempatkan</label><input id="asrama-student-options-search" type="search" wire:model.live.debounce.300ms="candidateSearch" placeholder="Cari nama atau NISN" autocomplete="off">
                        @forelse($candidates ?? [] as $candidate)
                        <button type="button" class="asrama-student-option" wire:click="selectStudent({{ $candidate->id }})" wire:key="candidate-{{ $candidate->id }}"><span><strong>{{ $candidate->name }}</strong><small class="block muted">NISN {{ $candidate->nisn ?: 'belum diisi' }}</small></span><x-simus-icon name="plus"/></button>
                        @empty<p class="caption p-4">Tidak ada santri tersedia. Santri harus aktif, belum ditempatkan, dan sesuai kategori asrama.</p>@endforelse
                        @if($candidates && $candidates->hasPages())<div class="mt-3">{{ $candidates->links(data: ['scrollTo' => false]) }}</div>@endif
                    </div>
                @endif
            </div>
            <button type="submit" wire:loading.attr="disabled" @disabled(!$studentId || !$asrama->is_active)><x-simus-icon name="plus"/>Tambah Santri</button>
        </form>
        <p class="caption">Hanya santri yang belum ditempatkan yang dapat dipilih. Data santri baru dikelola dari menu <a wire:navigate class="text-link" href="{{ route('santri.index') }}">Santri binaan</a>.</p>
    </section>
    <section class="panel"><div class="panel-heading"><h2>Asrama {{ $asrama->name }}</h2><span class="caption">{{ $students->total() }} santri sesuai pencarian</span></div><label>Cari santri di asrama<input type="search" wire:model.live.debounce.300ms="search" placeholder="Nama atau NISN"></label>
    <div class="table-wrap mt-4"><table><caption class="sr-only">Daftar santri {{ $asrama->name }}</caption><thead><tr><th>No</th><th>NISN</th><th>Nama santri</th><th>Kategori</th><th>Aksi</th></tr></thead><tbody>
    @forelse($students as $student)<tr wire:key="member-{{ $student->id }}"><td>{{ $students->firstItem() + $loop->index }}</td><td>{{ $student->nisn ?: '—' }}</td><td>{{ $student->name }}</td><td>{{ $student->gender ? ucfirst($student->gender) : '—' }}</td><td><div class="flex flex-wrap gap-2"><a wire:navigate class="text-link" href="{{ route('santri.show', $student) }}">Lihat</a><button class="button-secondary" wire:click="confirmRemove({{ $student->id }})">Keluarkan</button></div></td></tr>
    @empty<tr><td colspan="5" class="table-empty">Belum ada santri sesuai pencarian. Tambahkan santri menggunakan form di atas.</td></tr>@endforelse
    </tbody></table></div>
    {{ $students->links() }}
    </section>
    <flux:modal name="asrama-student-removal" wire:model="confirmingRemoval" wire:close="cancelRemove" class="admin-editor" :dismissible="false" :closable="false">
        <div class="space-y-4"><h2>Konfirmasi keluarkan santri</h2><p>Keluarkan <strong>{{ $pendingRemoval?->name }}</strong> dari {{ $asrama->name }}?</p><p class="caption">Identitas, catatan, nilai, dan rapor tetap tersimpan.</p><div class="flex justify-end gap-3"><button type="button" class="button-secondary" wire:click="cancelRemove">Batal</button><button type="button" wire:click="remove" wire:loading.attr="disabled">Keluarkan dari asrama</button></div></div>
    </flux:modal>
</div>
