@props(['name' => 'grid'])
@php($paths = [
'grid'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
'plus'=>'M12 5v14 M5 12h14',
'report'=>'M8 3H5a2 2 0 0 0-2 2v15h18V5a2 2 0 0 0-2-2h-3 M8 2h8v4H8z M7 11h10 M7 15h6',
'chart'=>'M4 3v17h17 M8 15v-4 M13 15V7 M18 15v-7',
'users'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8 M22 21v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
'home'=>'M3 10l9-7 9 7v11H3z M9 21v-8h6v8',
'check'=>'M5 12l4 4L19 6',
'clock'=>'M12 8v4l3 2 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
'arrow'=>'M5 12h14 M13 6l6 6-6 6',
'user'=>'M20 21v-2a7 7 0 0 0-14 0v2 M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
'logout'=>'M9 4H4v16h5 M10 12h11 M17 8l4 4-4 4',
'menu'=>'M4 6h16 M4 12h16 M4 18h16',
'photo'=>'M3 5h18v15H3z M3 16l5-5 5 5 3-3 5 5 M15 9h.01',
'calendar'=>'M4 5h16v16H4z M8 3v4 M16 3v4 M4 10h16',
'shield'=>'M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6z M8 12l3 3 5-6',
])
<svg {{ $attributes->merge(['class'=>'simus-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['grid'] }}"/></svg>
