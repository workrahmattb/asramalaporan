# SIMUS — design review dan implementasi

## Summary
UI awal membutuhkan perbaikan: aplikasi web untuk pembina dan pengelola asrama, dengan pekerjaan utama mengirim dan memantau laporan harian. Identitas yang dipertahankan adalah hijau pesantren yang tenang; elemen khasnya adalah ringkasan cakupan laporan hari ini, bukan skor kinerja.

## Temuan dan perbaikan
- **High — Navigasi tanpa posisi aktif**: `resources/views/layouts/simus.blade.php` awal tidak memiliki aria-current. `sidebars.md › Best practices`: “If you need to include two levels of hierarchy in a sidebar, use succinct, descriptive labels to title each group.” Diperbaiki dengan grup menu, ikon konsisten, posisi aktif, dan navigasi disclosure native pada layar kecil.
- **High — Hierarki aksi**: semua tombol awal menggunakan warna penuh. `buttons.md › Style`: “Use style — not size — to visually distinguish the preferred choice among multiple options.” Satu tombol primer; edit/draft memakai sekunder.
- **High — Kepadatan dashboard**: 37 nama tampil sebelum laporan terbaru. `layout.md › Visual hierarchy`: “Use progressive disclosure to make layouts cleaner and easier to interact with.” Enam nama awal dan disclosure daftar lengkap.
- **Critical — Target navigasi**: tautan menu awal hanya setinggi teks, belum memiliki target 44 px. `accessibility.md › Mobility`: “Offer sufficiently sized controls.” Target menu dan aksi minimal 44 px, area label checkbox minimal 56 px, skip link dan focus-visible ditambahkan.
- **Medium — Tampilan gelap**: aturan inline awal mengunci permukaan putih dan input putih. `dark-mode.md › Best practices`: “Ensure that your app looks good in both appearance modes.” Token semantik mengikuti prefers-color-scheme.

## Token dan layout
Enam peran warna, tanpa blur pada isi. Rasio dihitung dari hex dengan rumus luminansi WCAG:

| Mode | Peran | Foreground | Surface | Contrast |
|---|---|---|---|---|
| light | text | #202622 | #ffffff | 15.42:1 |
| light | secondary | #626b65 | #ffffff | 5.51:1 |
| light | accent | #176548 | #ffffff | 7.02:1 |
| light | signal | #98510d | #ffffff | 5.97:1 |
| dark | text | #eef2ef | #202522 | 13.77:1 |
| dark | secondary | #aab8af | #202522 | 7.55:1 |
| dark | accent | #85d8ad | #202522 | 9.21:1 |
| dark | signal | #f4bd79 | #202522 | 9.19:1 |

Typography: system UI (`-apple-system`, BlinkMacSystemFont, Segoe UI, sans-serif). Body 16–17 px, heading 28–36 px, section 18–20 px, metadata 13–14 px. Rem menjaga zoom dan pembesaran font. Data memakai tabular numerals. Body web mengikuti ukuran browser; bukan klaim penggunaan Dynamic Type native.

Regular: sidebar tetap, toolbar tanggal, konten maksimum 1180 px. Compact: header dan menu disclosure, isi satu kolom, aksi form tetap berada dalam alur agar tidak menutupi isi atau keyboard.

```text
Regular                        Compact
[Sidebar][Tanggal / identitas] [SIMUS] [Menu]
[       ][Judul + aksi       ] [Tanggal]
[       ][Cakupan hari ini   ] [Judul + aksi]
[       ][Statistik bulanan  ] [Cakupan hari ini]
[       ][Terbaru][Belum lapor] [Statistik → 1 kolom]
                               [Terbaru]
                               [Belum lapor]
```

## Craft notes
Ini utilitas pesantren: fokus pada kelengkapan laporan, nama pembina, dan tanggal. Hijau hanya untuk aksi utama/status positif; tidak ditambahkan dekorasi kaca, ilustrasi generik, atau motion yang tidak membantu. Rencana tidak menggunakan menu bar macOS atau kontrol iOS karena produk ini web responsif.

## Validasi dan batas
Uji browser, responsive, kontras, dan checks dicatat setelah implementasi. Screen reader/perangkat fisik belum diuji. Referensi berasal dari skill lokal `.agents/skills/apple-design/references/hig/`; file yang dibaca: accessibility, layout, typography, color, designing-for-ios, sidebars, buttons, entering-data, dark-mode, serta cross-platform dan code-audit.


### Hasil pemeriksaan
- Browser: dashboard pada 1440 × 1000 dan 390 × 844; form laporan pada lebar 320 px. `scrollWidth` sama dengan `innerWidth` pada halaman yang diperiksa.
- Navigasi mobile membuka semua menu yang berlaku. Menu desktop menandai halaman aktif dengan aria-current.
- Edit pembina membuka disclosure, menggulir ke editor, dan memfokuskan input pertama; pembatalan mengembalikan form tanpa menyimpan data.
- Sebelas checklist memiliki label lengkap dan area sentuh yang lebih tinggi dari 56 px pada lebar 320 px.
- Rata-rata ditampilkan sebagai tanda — bila belum ada laporan terkirim, agar tidak menyiratkan nilai 0% untuk hari tanpa laporan.
- 43 tes berhasil, 2 tes dilewati; PHPStan tanpa error. Build aset dan pemeriksaan format berhasil.
- Light mode diperiksa visual. Token dark mode dan reduced-motion tersedia pada CSS; pemeriksaan visual dark mode, screen reader, zoom 200%, dan perangkat fisik belum dilakukan.
- Satu elemen yang dihilangkan: daftar panjang semua pembina dari tampilan awal dashboard. Daftar lengkap tetap tersedia lewat disclosure.

Pratinjau: `docs/simus-desktop.jpg` dan `docs/simus-mobile.jpg`.

## Pengalaman khusus Musyrif/Musyrifah

- Layout `layouts/pembina.blade.php` memisahkan workspace personal dari administrasi. Shell dibatasi 672 px pada desktop dan memenuhi layar ponsel.
- Lima tujuan navigasi: Beranda, Riwayat, Lapor kegiatan, Rekap, Profil. Tujuan laporan ditonjolkan di tengah bawah, membuka form atau laporan hari ini sesuai status; tombol tidak langsung mengirim data. Ini mengikuti `tab-bars.md › Best practices`: “Use a tab bar to support navigation, not to provide actions.”
- Ringkasan personal menekankan nama, asrama, status hari ini, catatan terbaru, dan rekap pribadi. Riwayat memakai kartu tanggal, bukan tabel admin.
- Area navigasi tetap memiliki label, aria-current, focus-visible, target sentuh minimal 44 px, padding konten 128 px, dan tambahan safe-area untuk menghindari konten tertutup bar bawah.
- Aksi simpan dan kirim tetap berada di dalam form. Navigasi bawah tidak menjalankan aksi kirim atau menyimpan data.
- Tampilan admin/koordinator memakai layout administrasi yang sama seperti sebelumnya.

Pemeriksaan lanjutan versi pembina: login akun Musyrif berhasil; tombol tengah membuka form edit laporan hari ini; riwayat dan rekap personal dapat dibuka. Pada lebar 320 dan 390 px tidak ada overflow horizontal. Kelima target navigasi berukuran lebih dari 44 × 44 px. Pada bagian akhir form, batas bawah tombol Kirim berada di 625 px dan bar navigasi mulai di 756 px (viewport 390 × 844), sehingga aksi tidak tertutup. Seluruh 44 tes lulus (2 dilewati) dan PHPStan tanpa error. Pratinjau versi pembina: `docs/simus-pembina-mobile.jpg`.

## Modal data administrasi

Tambah/edit Pembina & penempatan, Asrama, dan Kegiatan harian menggunakan komponen `admin-data-modal` bersama (Flux/native dialog). Pemilihan modal sesuai tugas pengisian singkat yang terkait daftar; `modality.md › Best practices`: “Always give people an obvious way to dismiss a modal view.” Tombol Batal dan Tutup tersedia, Escape didukung, fokus masuk ke input pertama dan background menjadi inert. Klik di luar tidak menutup form secara tidak sengaja. Form dapat digulir pada layar kecil dan footer aksi tetap mudah dijangkau.

Validasi inline mempertahankan modal saat gagal. Pembatalan tidak mengubah record; simpan berhasil menutup modal. Filter pembina dipertahankan. Pengujian CRUD dilakukan pada database tes terpisah. Pemeriksaan browser mencakup tambah/edit dan validasi pembina, Escape, serta tambah/edit asrama dan kegiatan pada lebar 390 px. Semua data nyata tetap dipertahankan.
