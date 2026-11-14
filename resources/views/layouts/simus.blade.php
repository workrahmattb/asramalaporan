@if(auth()->user()->isPembina())
@include('layouts.pembina', ['slot'=>$slot])
@else
<!DOCTYPE html>
<html lang="id"><head>@include('partials.head')</head>
<body class="simus-app"><a class="skip-link" href="#main-content">Lewati ke konten</a>
<div class="app-shell">
<aside class="app-sidebar"><a wire:navigate class="brand" href="{{ route('dashboard') }}"><span class="brand-mark"><x-simus-icon name="report"/></span><span><strong>{{ config('app.name', 'Sistem Asrama') }}</strong><small>Syafa'aturrasul</small></span></a><x-simus-nav/>
<div class="sidebar-account"><div class="avatar">{{ auth()->user()->initials() }}</div><div class="account-copy"><strong>{{ auth()->user()->name }}</strong><span>{{ str_replace('_',' ',ucwords(auth()->user()->role,'_')) }}</span></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-button" aria-label="Keluar dari akun" title="Keluar dari akun"><x-simus-icon name="logout"/></button></form></div></aside>
<div class="workspace"><header class="app-toolbar"><a wire:navigate class="mobile-brand" href="{{ route('dashboard') }}">{{ config('app.name', 'Sistem Asrama') }}<span>Syafa'aturrasul</span></a><span class="toolbar-context">Ruang pembinaan asrama</span><time datetime="{{ today()->toDateString() }}"><x-simus-icon name="calendar"/>{{ today()->translatedFormat('l, d F Y') }}</time></header>
<details class="mobile-menu"><summary><x-simus-icon name="menu"/>Menu navigasi<span>{{ auth()->user()->initials() }}</span></summary><x-simus-nav/><form method="POST" action="{{ route('logout') }}">@csrf<button class="button-secondary">Keluar dari akun</button></form></details>
<main id="main-content" class="app-content" tabindex="-1">
@if(session('success'))<div role="status" class="notice notice-success"><x-simus-icon name="check"/><span>{{ session('success') }}</span></div>@endif
@if($errors->any())<div role="alert" class="notice notice-error"><div><strong>Periksa kembali isian berikut</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
<livewire:active-academic-period />
{{ $slot }}
<footer class="workspace-footer"><span>{{ config('app.name', 'Sistem Asrama') }} · Pondok Pesantren Syafa'aturrasul</span><span>Pembinaan terarah, laporan tercatat.</span></footer>
</main></div></div>@persist('toast')<flux:toast.group><flux:toast/></flux:toast.group>@endpersist @fluxScripts</body></html>

@endif
