<!DOCTYPE html>
<html lang="id"><head>@include('partials.head')</head>
<body class="simus-app pembina-app"><a class="skip-link" href="#main-content">Lewati ke konten</a>
@php
$pembinaToday=auth()->user()->reports()->whereDate('report_date',today())->first();
$multipleAsramas=auth()->user()->assignedAsramas()->count()>1;
$laporUrl=$pembinaToday && !$multipleAsramas ? route(auth()->user()->can('update',$pembinaToday) ? 'reports.edit' : 'reports.show',$pembinaToday) : route('reports.create');
$laporActive=request()->routeIs('reports.create','reports.edit','reports.show');
@endphp
<div class="pembina-shell"><header class="pembina-header"><a wire:navigate class="pembina-brand" href="{{ route('dashboard') }}"><span class="brand-mark"><x-simus-icon name="report"/></span><span>{{ config('app.name', 'Sistem Asrama') }}<small>Teman pembinaan harian</small></span></a><a wire:navigate href="{{ route('profile.edit') }}" class="avatar pembina-profile" aria-label="Buka profil {{ auth()->user()->name }}">{{ auth()->user()->initials() }}</a></header>
<main id="main-content" class="pembina-content" tabindex="-1">
@if(session('success'))<div role="status" class="notice notice-success"><x-simus-icon name="check"/><span>{{ session('success') }}</span></div>@endif
@if($errors->any())<div role="alert" class="notice notice-error"><div><strong>Periksa kembali isian berikut</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
<livewire:active-academic-period />
{{ $slot }}
@if(request()->routeIs('profile.edit','security.edit','appearance.edit'))<form class="pembina-logout" method="POST" action="{{ route('logout') }}">@csrf<button class="button-secondary"><x-simus-icon name="logout"/>Keluar dari akun</button></form>@endif
</main>
<footer class="pembina-signoff">Pondok Pesantren Syafa'aturrasul</footer>
</div>
<nav class="pembina-tabbar" aria-label="Navigasi pembina"><div class="pembina-tabs">
<a wire:navigate href="{{ route('dashboard') }}" class="pembina-tab {{ request()->routeIs('dashboard*') ? 'is-current' : '' }}" @if(request()->routeIs('dashboard*')) aria-current="page" @endif><x-simus-icon name="home"/><span>Beranda</span></a>
<a wire:navigate href="{{ route('santri.index') }}" class="pembina-tab {{ request()->routeIs('santri.*','assessments.*') ? 'is-current' : '' }}" @if(request()->routeIs('santri.*','assessments.*')) aria-current="page" @endif><x-simus-icon name="users"/><span>Santri</span></a>
<a wire:navigate href="{{ $laporUrl }}" class="pembina-tab pembina-lapor {{ $laporActive ? 'is-current' : '' }}" aria-label="Lapor kegiatan{{ $pembinaToday && !$multipleAsramas ? ', buka laporan hari ini' : '' }}" @if($laporActive) aria-current="page" @endif><span class="lapor-orb"><x-simus-icon :name="$pembinaToday ? 'report' : 'plus'"/></span><span>Lapor kegiatan</span></a>
<a wire:navigate href="{{ route('reports.index') }}" class="pembina-tab {{ request()->routeIs('reports.index','reports.monthly') ? 'is-current' : '' }}" @if(request()->routeIs('reports.index','reports.monthly')) aria-current="page" @endif><x-simus-icon name="clock"/><span>Riwayat</span></a>
<a wire:navigate href="{{ route('profile.edit') }}" class="pembina-tab {{ request()->routeIs('profile.edit','security.edit','appearance.edit') ? 'is-current' : '' }}" @if(request()->routeIs('profile.edit','security.edit','appearance.edit')) aria-current="page" @endif><x-simus-icon name="user"/><span>Profil</span></a>
</div></nav>
@persist('toast')<flux:toast.group><flux:toast/></flux:toast.group>@endpersist @fluxScripts
</body></html>
