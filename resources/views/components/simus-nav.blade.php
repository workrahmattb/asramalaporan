@php
$groups = ['Ruang kerja'=>[
 ['dashboard','Ringkasan','grid','dashboard*'],
 ['reports.index','Riwayat laporan','report','reports.index'],
 ['reports.monthly','Rekap bulanan','chart','reports.monthly'],
]];
if (auth()->user()->isPembina() || auth()->user()->isAdmin()) array_splice($groups['Ruang kerja'],1,0,[['reports.create','Isi laporan','plus','reports.create|reports.edit']]);
if (!auth()->user()->isPembina()) $groups['Ruang kerja'][]=['reports.monitoring','Monitoring','clock','reports.monitoring'];
if (auth()->user()->isAdmin()) $groups['Administrasi']=[['admin.users','Pembina & penempatan','users','admin.users'],['admin.asramas','Asrama','home','admin.asramas'],['admin.tasks','Kegiatan harian','check','admin.tasks']];
$groups['Pembinaan santri']=[['santri.index','Santri binaan','users','santri.*']];
if(!auth()->user()->isPembina()) $groups['Pembinaan santri'][]=['assessments.index','Pemeriksaan penilaian','shield','assessments.*'];
if(auth()->user()->isAdmin()) $groups['Pembinaan santri'][]=['admin.academic-settings','Tahun Ajaran','calendar','admin.academic-settings|admin.assessment-periods'];
if(auth()->user()->isAdmin()) $groups['Pembinaan santri'][]=['admin.assessment-subjects','Aspek penilaian','check','admin.assessment-subjects'];
$groups['Akun']=[['profile.edit','Profil & keamanan','user','profile.edit|security.edit|appearance.edit']];
@endphp
<nav aria-label="Navigasi utama" class="simus-nav">
@foreach($groups as $label=>$links)<div class="nav-group"><p class="nav-label">{{ $label }}</p>@foreach($links as [$route,$text,$icon,$pattern])<a wire:navigate href="{{ route($route) }}" class="nav-link {{ request()->routeIs(...explode('|',$pattern)) ? 'is-active' : '' }}" @if(request()->routeIs(...explode('|',$pattern))) aria-current="page" @endif><x-simus-icon :name="$icon"/><span>{{ $text }}</span></a>@endforeach</div>@endforeach
</nav>
