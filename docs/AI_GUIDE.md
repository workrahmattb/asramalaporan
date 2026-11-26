# Panduan AI — Sistem Asrama

Terakhir diselaraskan dengan kode: **24 November 2026**. Ini adalah panduan kondisi aplikasi saat ini, bukan rencana fitur atau catatan percakapan. Nama folder kerja `kerjaasrama`; nama produk **Sistem Asrama**. `SIMUS` pada nama class, command, konfigurasi, dan migration adalah nama teknis lama; jangan menggantinya massal.

## Urutan membaca proyek baru

1. Baca [AGENTS.md](../AGENTS.md) untuk aturan kerja repository.
2. Baca dokumen ini seluruhnya untuk aturan bisnis dan keputusan aktif.
3. Baca [ARCHITECTURE.md](ARCHITECTURE.md) untuk peta route, komponen, service, policy, data, dan tes.
4. Baca [OPERATIONS.md](OPERATIONS.md) sebelum menjalankan setup, pengujian, migrasi, atau deployment.
5. Baca kode modul terkait dan tesnya sebelum mengubahnya. Periksa versi dependency dari Composer/package.json; jangan menebak API.

README adalah ringkasan penggunaan. `memory.md`, jika tersedia lokal, adalah riwayat tambahan yang **tidak ikut Git**. Keputusan dalam memory dapat sudah dibatalkan; nomor bagian terbaru bukan pengganti pemeriksaan kode. Dokumen UI lama diberi label historis. Jika dokumentasi dan kode berbeda, laporkan perbedaannya dan verifikasi perilaku melalui kode/tes; jangan diam-diam mengubah perilaku untuk menyesuaikan dokumen.

## Tujuan dan batas modul

Aplikasi monolith Laravel untuk dua pekerjaan yang berbeda:

- **Laporan kegiatan harian pembina:** checklist kegiatan, kendala, catatan, foto, monitoring, statistik, Excel.
- **Pembinaan dan rapor santri:** identitas/penempatan santri, catatan pembinaan, tahun/semester global, nilai per aspek, pemeriksaan, leger Excel, PDF.

Nilai santri tidak dihitung otomatis dari kegiatan harian atau catatan pembinaan. Belum ada modul absensi santri, peringkat, nilai berbobot, WhatsApp, atau PWA. Judul salah satu kegiatan awal memuat kata absensi; itu hanya teks checklist, bukan modul absensi.

## Stack dan pola implementasi

- Lingkungan kerja PHP 8.4; Composer mensyaratkan PHP ^8.3. Versi terkunci saat penyusunan: Laravel 13.42, Livewire 4.4.8, Flux 2.21, Dompdf wrapper 3.1.2/Dompdf 3.1.6. Periksa kembali lock/installed version saat melanjutkan.
- Tailwind 4; build memakai `npm run build` (Vite Plus). Autentikasi Laravel Fortify; registrasi publik dinonaktifkan.
- Class Livewire di `app/Livewire`, Blade di `resources/views/livewire`, service bisnis di `app/Services`, policy di `app/Policies`.
- Layout utama `resources/views/layouts/simus.blade.php`, navigasi `resources/views/components/simus-nav.blade.php`, styling khusus `resources/css/simus.css`.
- Navigasi internal menggunakan `wire:navigate`/redirect SPA. Filter, pagination, simpan, dan modal memakai Livewire. PDF dan unduhan file memakai endpoint HTTP biasa; PDF dibuka di tab baru.
- UI pembina memakai navigasi bawah: Beranda, Santri, Lapor kegiatan, Riwayat, Profil. Pertahankan gaya proyek; jangan mengubah pembina menjadi tampilan dashboard admin.

## Role dan cakupan akses

`super_admin` dan `admin` sama-sama memenuhi `User::isAdmin()`. `musyrif` dan `musyrifah` memenuhi `isPembina()`.

| Kemampuan | Admin / Super Admin | Koordinator | Musyrif / Musyrifah |
|---|---|---|---|
| Kelola akun, asrama, kegiatan, identitas santri | Ya | Tidak | Tidak |
| Penempatan beberapa asrama per pembina | Mengatur | Memiliki penugasan | Memiliki penugasan |
| Buat laporan kegiatan harian | Ya, perlu penempatan untuk laporan baru | Tidak | Ya, asrama penugasan |
| Lihat laporan harian | Semua | Asrama penugasan | Laporan milik sendiri |
| Pemeriksaan laporan harian | Semua | Asrama penugasan | Tidak |
| Lihat identitas santri | Semua | Asrama penugasan saat ini | Asrama penugasan saat ini |
| Tambah catatan pembinaan | Ya | Tidak | Santri aktif dan ditempatkan dalam cakupan |
| Isi nilai | Ya | Tidak | Asrama penugasan, periode terbuka, rapor dapat diedit |
| Pemeriksaan penilaian | Semua | Asrama penugasan | Tidak |
| Cetak PDF rapor tersimpan | Semua | Asrama penugasan | Asrama penugasan |

Semua akses memerlukan akun aktif. Middleware `EnsureActive` juga memeriksa kesiapan kredensial dan kewajiban penggantian password jika flag tersebut aktif. Tabel ini ringkasan; policy, scope `visibleTo`, service, dan middleware adalah rujukan detail. Menyembunyikan tombol saja tidak cukup untuk otorisasi.

## Aturan aktif: akun dan penempatan pembina

- Pembina/koordinator dapat memiliki lebih dari satu asrama. Riwayat di pivot `asrama_user` memiliki role penugasan serta tanggal mulai/akhir.
- `assignedAsramas($date)` memeriksa role penugasan dan rentang tanggal. Jangan mengganti dengan relasi semua asrama tanpa batas tanggal/role.
- Edit penempatan mempertahankan riwayat: pilihan yang dilepas ditutup, bukan menghapus riwayat laporan.
- Password seeder default `123456` di seluruh environment adalah keputusan eksplisit pemilik. Form password saat ini tidak mensyaratkan 12 karakter. Jangan memperkenalkan aturan lama kembali tanpa permintaan baru. Password tetap di-hash; jangan memuat `.env` atau password pengguna ke dokumentasi/log.
- Hapus akun sendiri tidak ditampilkan untuk pembina. Perubahan profil tidak berarti izin menghapus identitas atau riwayat bisnis.

## Aturan aktif: laporan harian

1. Pembina memilih asrama penugasannya dan mengisi laporan. Satu laporan per **user + tanggal + asrama**.
2. Laporan baru tanggal lampau hanya untuk admin. Tanggal/asrama laporan lama tidak dipindahkan mengikuti edit penempatan.
3. Kegiatan aktif disalin sebagai item snapshot ketika laporan dibuat; perubahan master kegiatan tidak menulis ulang laporan lama.
4. Draft dapat disimpan; kirim laporan langsung tanpa popup konfirmasi tambahan. Checklist dapat kosong. Persentase dihitung dari item tersimpan, bukan input persentase client.
5. Status: `draft` → `submitted`; pemeriksa dapat menetapkan `verified` atau `revision`. Admin dapat membuka laporan verified kembali. Perubahan dicatat di `report_audits`.
6. Pembina mengedit laporan milik sendiri selama belum verified; admin memiliki akses koreksi sesuai policy/service.
7. Foto memakai upload berkas biasa, **bukan capture kamera**. Maksimal lima foto total per laporan, masing-masing foto baru maksimal **1953 KiB**; tidak ada kompresi JavaScript aktif.
8. Foto privat di disk local, melalui route foto ber-policy. Jangan memindahkan upload laporan ke public atau membuka URL storage langsung.
9. Rekap bulanan admin/koordinator memakai filter kategori pembina Semua / Musyrif (Putra) / Musyrifah (Putri), menggantikan dropdown asrama. Daftar nama pembina mengikuti kategori; mengganti kategori mengosongkan pilihan nama dan kembali ke halaman pertama. Filter kategori berlaku pada baris rekap dan Excel berdasarkan assignment_role riwayat penempatan. Rekap pribadi pembina tetap memakai tampilan semula.
10. Riwayat dan monitoring menampilkan kendala serta catatan tambahan. Statistik dan ekspor harus tetap menghitung seluruh data sesuai filter, bukan hanya halaman tabel yang terlihat.

## Aturan aktif: santri dan asrama

- Pada identitas santri, hanya nama wajib. NISN opsional dan unik jika diisi; pertahankan nol di depan sebagai string. Jangan kembali menggunakan NIS.
- Tempat/tanggal lahir, nama ayah/ibu, kontak ayah/ibu, kategori, dan asrama dapat dilengkapi kemudian.
- Santri tanpa penempatan hanya terlihat dalam cakupan admin. Pembina melihat santri aktif dari seluruh asrama penugasannya.
- Santri mempunyai satu penempatan saat ini; riwayat bertanggal di `santri_placements`. Pembina dapat multi-asrama, **santri bukan multi-penempatan aktif**.
- Admin Asrama: pencarian nama/kode/lokasi dan urutan nama A–Z, pagination 10.
- **Asrama → Kelola santri:** form inline, cari nama/NISN, centang banyak santri, ringkasan pilihan, Batal pilih atau Batalkan semua, lalu Tambah Santri(jumlah). Pilihan bertahan lintas pencarian/halaman kandidat.
- Hanya santri aktif, belum ditempatkan, dan kategori cocok yang boleh ditambahkan. Asrama harus aktif. Batch memakai transaksi; satu pilihan tidak valid/stale membatalkan seluruh batch. Tidak otomatis mengambil santri dari asrama lain.
- **Keluarkan** di Kelola Santri mengakhiri penempatan, dengan konfirmasi; identitas, catatan, penilaian, dan rapor tetap.
- **Hapus** di Data Santri admin adalah hapus permanen dengan konfirmasi, hanya jika tidak punya guidance note atau assessment (termasuk draft). Penempatan dibersihkan bersama identitas. Santri yang memiliki riwayat harus dinonaktifkan.
- Pembuatan identitas baru dilakukan di Santri binaan; Kelola Santri Asrama tidak mempunyai mode membuat identitas baru.

## Aturan aktif: tahun ajaran dan penilaian

1. **Tahun Ajaran** adalah satu-satunya pengaturan global tahun/semester. Master aspek ada di **Aspek penilaian**, bukan dibuat ulang pada tiap halaman pembina.
2. Membuat tahun otomatis membuat Ganjil dan Genap, awalnya nonaktif, menyalin aspek master aktif dengan skala awal A/B/C/D.
3. Admin mengaktifkan tahun lalu semester. Aktivasi global menggantikan periode sebelumnya. Tanggal adalah keterangan; status aktif dikendalikan admin, bukan otomatis kalender.
4. Aspek/skala dapat disesuaikan sebelum dipakai. Setelah assessment ada, rubrik terkait dikunci untuk menjaga snapshot. Tahun yang sudah mempunyai penilaian tidak boleh dihapus.
5. Pembina membuka Santri → Rapor Asrama → pilih asrama dan aspek → Isi nilai seluruh santri.
6. **Khusus tabel input nilai per aspek: seluruh santri aktif ditampilkan tanpa pagination**, urutan nama, kolom No. 1..n. Jangan kembalikan batas simpan 10 baris pada `AspectGradeService`.
7. Simpan nilai menyimpan seluruh baris yang dapat diedit sebagai draft, menampilkan “Menyimpan…” lalu notifikasi “Nilai berhasil disimpan” hanya setelah sukses. Perubahan belum tersimpan dijaga saat navigasi.
8. Nilai kosong mengosongkan aspek pada rapor yang sudah ada. Santri tanpa rapor dan tanpa nilai tidak dibuatkan draft kosong otomatis. Aspek lain dan catatan harus dipertahankan.
9. Satu rapor per santri/periode; identitas/asrama/periode serta aspek/indikator/skala disalin saat rapor pertama dibuat. `version` dan transaksi mencegah penimpaan perubahan pengguna lain.
10. Setelah semua aspek lengkap, Catatan & kirim rapor mengisi perkembangan/fokus dan mengirim ke pemeriksaan. Status: `draft` → `submitted` → `approved` atau `revision`; admin dapat reopen approved ke revision dengan alasan.
11. Submitted/approved tidak dapat diubah melalui input batch. **Pencetakan tidak memerlukan persetujuan**; ini berbeda dari aturan penguncian edit. Jangan menghapus alur pemeriksaan hanya karena cetak dibuka.
12. Santri pindah setelah rapor periode dibuat: rapor historis tidak dipindah otomatis. Koreksi lintas penempatan perlu ditangani admin sesuai policy/service.

## Aturan aktif: PDF dan tampilan daftar

- Cetak per santri atau satu asrama langsung `application/pdf`, inline di tab baru. Semua status rapor tersimpan boleh dicetak; tetap periksa akses asrama/akun aktif.
- PDF dihasilkan server memakai Dompdf; remote/PHP/JavaScript rendering dimatikan. Response private/no-store, tidak disimpan di public. Excel leger tetap terpisah.
- Tampilan PDF di `resources/views/assessments/sheet.blade.php` dan `print-styles.blade.php`, konfigurasi `config/rapor.php`.
- Kop Putra/Putri mengikuti kategori asrama **pada asrama_id rapor**, bukan role pengguna atau penempatan santri terbaru. Kategori kosong memakai nama pondok umum. Kategori asrama ini dibaca dari master saat cetak, bukan snapshot gender terpisah.
- Logo resmi `public/logo-pondok-putra.png` dan `public/logo-pondok-putri.png`, ditanam data PNG base64 agar tidak bergantung URL hosting.
- Preferensi tampilan terakhir: nama pondok **15 pt kapital**, alamat **10 pt kapital**; judul laporan **12 pt kapital bergaris bawah**, subjudul Inggris **10 pt bold italic**. Logo 14 mm. Jangan mengulang desain lembut/title-case yang sudah ditolak pengguna.
- Kota/penandatangan ada di config rapor; tanggal reviewed_at jika ada, tanggal cetak bila belum diperiksa. Pembina dari user pengisi, ruang Walisantri kosong.
- Contoh sembilan aspek diuji satu halaman A4; panjang konten dapat menambah halaman. Saat mengubah PDF, render dan lihat hasilnya, jangan hanya mengandalkan tes HTTP.
- Daftar utama memakai pagination 10, kecuali **input nilai per aspek**. No. pada tabel paginasi berlanjut dengan `firstItem() + $loop->index`; tabel tanpa pagination menggunakan `iteration`. Tabel identitas/tanda tangan PDF bukan daftar berulang yang perlu nomor.

## Cara melanjutkan pekerjaan

- Jangan mengulang implementasi dari awal. Pertahankan data yang sudah dipakai di hosting.
- Baca tes modul terkait di peta arsitektur; uji batas akses dan kegagalan penting saat mengubah logika. Styling murni tidak perlu tes baru.
- Jangan install dependency tanpa persetujuan. Jangan menyalin UI/code proyek referensi ke sini tanpa menyesuaikan aturan dan hak akses proyek ini.
- Setelah perubahan PHP jalankan Pint sesuai AGENTS. Build untuk perubahan aset frontend; CSS PDF inline tidak membutuhkan build frontend.
- Ubah dokumentasi aktif jika perilaku berubah. Tambahkan riwayat lokal di memory.md bila tersedia, tetapi pastikan aturan terbaru tetap tercantum pada dokumen yang ikut Git.
- Jangan menganggap hasil tes lokal membuktikan deployment atau kapasitas hosting. Jangan mengklaim sudah push/deploy/backup bila belum dilakukan.
