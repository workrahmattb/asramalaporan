# SIMUS — Sistem Monitoring Musyrifah

Aplikasi Laravel monolith untuk laporan tugas harian Pondok Pesantren Syafa'aturrasul. Menggunakan Laravel 13 / Livewire 4 yang sudah terpasang pada proyek awal (PRD menyebut Laravel 12), Tailwind, dan autentikasi Fortify. Struktur komponen mengikuti direktori `app/Livewire/Dashboard`, `Reports`, dan `Admin`.

## Menjalankan lokal

```sh
composer install
npm install
cp .env.example .env # hanya untuk instalasi baru; jangan menimpa konfigurasi yang sudah ada
php artisan key:generate
```

Untuk SQLite lokal, buat `database/database.sqlite` dan gunakan `DB_CONNECTION=sqlite`. Untuk MySQL/MariaDB, atur `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` di `.env`, lalu:

```sh
php artisan migrate --seed
php artisan simus:admin admin@pesantren.sch.id "Administrator"
npm run build
php artisan serve
```

Perintah admin meminta password secara tersembunyi (minimal 12 karakter). Seeder sekarang membuat akun bootstrap Administrator; lihat bagian Seeder di bawah. Login lalu buat asrama dan akun Musyrifah/koordinator melalui menu admin. Penempatan mulai berlaku pada hari akun ditempatkan; tanggal awal riwayat dapat dikelola lewat database jika diperlukan saat migrasi data lama. Admin dapat mempunyai penempatan asrama untuk mengisi laporan sendiri.

## Fitur

- Dashboard per role, akun aktif, registrasi publik dinonaktifkan.
- Master asrama, kegiatan, akun dan penempatan; 11 kegiatan awal di-seed tanpa membuat akun demo.
- Laporan draft dan terkirim, checklist opsional, kendala, catatan, maksimal 5 foto masing-masing 5 MB.
- Snapshot nama/urutan/daftar kegiatan, persentase server, indeks unik laporan dan item.
- Foto privat pada disk `local`, hanya tersedia melalui endpoint yang memeriksa policy.
- Riwayat dengan pagination dan filter bulan/tanggal/asrama/status.
- Verifikasi, permintaan revisi dengan catatan, admin membuka laporan terverifikasi; perubahan laporan/status tercatat di `report_audits`.
- Monitoring hari ini, daftar belum melapor, rekap bulanan berdasarkan riwayat penempatan sampai hari ini, rata-rata tanpa draft, dan export `.xlsx` dengan cakupan akses yang sama.
- Zona waktu Asia/Jakarta; validasi inti dalam Bahasa Indonesia.

Pelaporan tanggal lampau untuk laporan baru dibatasi ke admin pada implementasi awal. Laporan lama milik sendiri tetap dapat diedit selama belum diverifikasi. Form admin saat ini mengelola satu penempatan aktif per pengguna; relasi database mendukung beberapa asrama koordinator.

## Validasi

```sh
php artisan test
vendor/bin/pint --test
vendor/bin/phpstan analyse --debug --no-progress
npm run build
```

`--debug` menjalankan pemeriksaan tipe tanpa worker paralel yang memerlukan socket lokal. Tes memakai database SQLite in-memory terpisah.

## Deployment

Arahkan document root ke `public`, gunakan HTTPS, `APP_DEBUG=false`, set kredensial database dan layanan email untuk reset password, dan jalankan migrasi, build, serta `php artisan optimize`. PHP memerlukan ekstensi database, fileinfo, dan zip (export XLSX). Pastikan `storage` dan `bootstrap/cache` dapat ditulis. Tidak perlu `storage:link` untuk foto laporan privat. Atur `upload_max_filesize` minimal 5M dan `post_max_size` di atas 25M.

Backup database dan `storage/app/private` harus dijadwalkan pada hosting. Deployment hosting, konfigurasi email, uji MySQL/MariaDB, dan pemeriksaan visual pada perangkat fisik belum dilakukan. Notifikasi WhatsApp, PWA, PDF, penilaian berbobot, dan integrasi eksternal termasuk fase lanjutan PRD.


## Seeder data awal pembina

Jalankan `php artisan migrate` lalu `php artisan db:seed`. Seeder terpisah dijalankan berurutan: RoleSeeder (menggunakan kolom role yang ada), AdminSeeder, AsramaSeeder, MusyrifSeeder, dan MusyrifahSeeder. Sebelas kegiatan awal tetap di-seed.

Data rincian sumber berisi **12 kelompok putra**, bukan 11 seperti total pada dokumen. Semua rincian dipertahankan: 12 putra + 14 putri = **26 kelompok**, 22 Musyrif + 15 Musyrifah = **37 pembina**, total **38 pengguna** termasuk Administrator. Nama kelompok tetap utuh, termasuk kelompok yang mengandung `&`. `asramas.unit` digunakan untuk gedung Khodijah/Hafshah/Aisyah; tidak ditambahkan kolom building yang redundan. `gender` ditambahkan karena belum ada representasi setara.

Admin development: `admin@ppsyaaturrasul.com`, password bootstrap `123456`, role `super_admin`. Pada production, wajib isi `SIMUS_ADMIN_PASSWORD` di environment dengan minimal 12 karakter, huruf besar/kecil, angka, dan simbol sebelum seeding. Password tidak dicetak ke log. Admin production diwajibkan mengganti password saat login pertama. Rerun tidak mereset password akun yang sudah ada; jika database development dibawa ke production, password bootstrap lama diwajibkan diganti sebelum akses fitur.

Pada production, pembina memakai identifier internal `simus-musyrif-NNN@accounts.simus.invalid` / `simus-musyrifah-NNN@accounts.simus.invalid`, `seed_key` unik, password acak berbeda, status data aktif, dan `credentials_ready=false`. Identifier ini bukan email pribadi. Login dan notifikasi reset/verifikasi email diblokir sampai admin membuka **Data Pengguna & Penempatan**, memilih Edit, menetapkan email asli yang dikonfirmasi dan password baru minimal 12 karakter. Sampaikan kredensial awal melalui kanal pribadi. Pembina wajib menetapkan password baru sebelum membuka fitur. Filter asrama dan gender tersedia pada daftar pengguna.

Seeder tidak menghapus data tambahan, tidak mereset kredensial pembina, dan tidak mengubah atau membuat ulang penempatan jika pengguna sudah memiliki riwayat. Kode asrama dan `seed_key` adalah identitas tetap; jangan mengganti kode asrama atau seed_key pengguna saat menambah data. Jumlah target hanya berlaku pada database tanpa data tambahan. Migrasi bersifat penambahan; tidak menggunakan `migrate:fresh` atau `db:wipe`.


### Kredensial pembina untuk development

Pada `APP_ENV=local` atau `testing`, `DatabaseSeeder` juga menjalankan `DevelopmentPembinaSeeder`. Seluruh 37 pembina baru memakai email berdasarkan nama dengan domain `@syafaaturrasul.com` dan password **123456** yang disimpan melalui `Hash::make()`. Akun dapat langsung login tanpa kewajiban ganti password, hanya untuk development. Email development ditandai terverifikasi lokal; ini tidak membuat mailbox sungguhan.

Seeder development hanya menginisialisasi akun seeded yang masih `credentials_ready=false`; rerun tidak mereset password yang sudah dipersonalisasi, email yang sudah ditetapkan, atau akun admin. Menjalankan seeder ini secara langsung di production akan ditolak. Production tetap memakai kredensial acak dan mekanisme penetapan akun sebelumnya. Jangan membawa database development ke production tanpa menetapkan ulang kredensialnya.

## Navigasi Livewire SPA

Perpindahan halaman internal (dashboard, menu admin, navigasi bawah pembina, riwayat/detail/edit laporan, rekap dan profil) menggunakan `wire:navigate`. Redirect setelah simpan laporan juga memakai navigasi SPA. Login/logout, unduh Excel, foto di tab baru, dan anchor lokal tetap menggunakan alur browser biasa.

Form laporan mengingat perubahan sejak pengguna mengetik dan meminta konfirmasi sebelum pindah melalui SPA maupun meninggalkan halaman biasa. Redirect dari penyimpanan yang berhasil tidak memunculkan peringatan ini. Toast tetap dipersist, sedangkan navigasi dinamis dirender kembali agar status aktif dan tujuan laporan hari ini tetap akurat.

## GitHub dan hosting dengan git pull

Repository menyertakan source, migration/seeder, `composer.lock`, `package-lock.json`, `.env.example`, dan **hasil build `public/build`**. Dependensi, `.env` asli, cache, log, database lokal, upload privat, screenshot pengujian, dan `memory.md` diabaikan melalui `.gitignore`.

Setiap mengubah frontend, jalankan `npm ci` (jika dependensi berubah) lalu `npm run build` **sebelum commit/push**. Commit perubahan source beserta hasil build agar hosting tidak perlu Node.js.

Instalasi pertama di hosting:

1. Clone repository dan arahkan document root domain ke direktori `public`, bukan root proyek.
2. Jalankan `composer install --no-dev --prefer-dist --optimize-autoloader`.
3. Salin `.env.example` menjadi `.env` hanya pada instalasi pertama. Isi konfigurasi production: `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS, koneksi MySQL, dan layanan email.
4. Jalankan `php artisan key:generate` **hanya saat instalasi pertama**. Pertahankan APP_KEY dan `.env` tersebut pada update berikutnya.
5. Jalankan `php artisan migrate --force`. Jika membutuhkan data awal, isi `SIMUS_ADMIN_PASSWORD` kuat lalu jalankan `php artisan db:seed --force` satu kali sesuai kebutuhan. Seeder development tidak dijalankan pada production.
6. Pastikan `storage` dan `bootstrap/cache` writable, lalu jalankan `php artisan optimize`.

Update berikutnya setelah backup database dan storage:

```sh
git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
```

Tidak perlu mengulang seeder atau `key:generate` pada setiap update. Jangan menimpa `.env` hosting, database, atau `storage/app/private`; data tersebut tidak dikelola Git. PHP hosting harus sesuai batas dependensi Composer dan menyediakan ekstensi database, fileinfo, serta zip untuk export Excel.

`.gitignore` hanya mencegah file baru ditambahkan. Jika sebelumnya file sensitif sudah pernah masuk commit, mengubah `.gitignore` tidak menghapusnya dari index atau riwayat Git. Saat pengaturan ini dibuat, direktori proyek belum memiliki `.git`; tidak ada commit atau push yang dilakukan oleh perubahan ini.
