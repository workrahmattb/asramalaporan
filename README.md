# Sistem Asrama — Monitoring Musyrif/Musyrifah

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

Perintah admin meminta password secara tersembunyi (wajib diisi). Seeder sekarang membuat akun bootstrap Administrator; lihat bagian Seeder di bawah. Login lalu buat asrama dan akun Musyrifah/koordinator melalui menu admin. Penempatan mulai berlaku pada hari akun ditempatkan; tanggal awal riwayat dapat dikelola lewat database jika diperlukan saat migrasi data lama. Admin dapat mempunyai penempatan asrama untuk mengisi laporan sendiri.

## Fitur

- Dashboard per role, akun aktif, registrasi publik dinonaktifkan.
- Master asrama, kegiatan, akun dan penempatan; 11 kegiatan awal di-seed tanpa membuat akun demo.
- Laporan draft dan terkirim, checklist opsional, kendala, catatan, maksimal 5 foto baru masing-masing di bawah 2 MB.
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

Arahkan document root ke `public`, gunakan HTTPS, `APP_DEBUG=false`, set kredensial database dan layanan email untuk reset password, dan jalankan migrasi, build, serta `php artisan optimize`. PHP memerlukan ekstensi database, fileinfo, dan zip (export XLSX). Pastikan `storage` dan `bootstrap/cache` dapat ditulis. Tidak perlu `storage:link` untuk foto laporan privat. Atur `upload_max_filesize` minimal 2M dan `post_max_size` di atas 10M.

Backup database dan `storage/app/private` harus dijadwalkan pada hosting. Deployment hosting, konfigurasi email, uji MySQL/MariaDB, dan pemeriksaan visual pada perangkat fisik belum dilakukan. Notifikasi WhatsApp, PWA, PDF, penilaian berbobot, dan integrasi eksternal termasuk fase lanjutan PRD.


## Seeder data awal pembina

Jalankan `php artisan migrate` lalu `php artisan db:seed`. Seeder terpisah dijalankan berurutan: RoleSeeder (menggunakan kolom role yang ada), AdminSeeder, AsramaSeeder, MusyrifSeeder, dan MusyrifahSeeder. Sebelas kegiatan awal tetap di-seed.

Data rincian sumber berisi **12 kelompok putra**, bukan 11 seperti total pada dokumen. Semua rincian dipertahankan: 12 putra + 14 putri = **26 kelompok**, 22 Musyrif + 15 Musyrifah = **37 pembina**, total **38 pengguna** termasuk Administrator. Nama kelompok tetap utuh, termasuk kelompok yang mengandung `&`. `asramas.unit` digunakan untuk gedung Khodijah/Hafshah/Aisyah; tidak ditambahkan kolom building yang redundan. `gender` ditambahkan karena belum ada representasi setara.

Akun seeder terbaru di semua environment, termasuk production:

- Admin: `admin@ppsyaaturrasul.com`, password `123456`.
- 37 pembina: email berdasarkan nama pada domain `@syafaaturrasul.com`, password `123456`.
- Password tetap disimpan sebagai hash. Login siap dan tidak diwajibkan mengganti password pertama untuk akun hasil seeder.
- `SIMUS_ADMIN_PASSWORD` tidak lagi diperlukan oleh seeder.
- Reseed biasa mempertahankan password yang sudah dipersonalisasi. Untuk secara sengaja mengembalikan password akun bawaan ke `123456`, jalankan `php artisan db:seed --class=ResetSeededPasswordsSeeder --force` setelah seeder utama. Perintah tersebut hanya menargetkan admin bootstrap dan identitas 37 pembina dari katalog, tanpa mengubah laporan atau penempatan.
- Password bersama mudah ditebak bila hosting dapat diakses publik. Alamat email dalam database tidak membuat mailbox sungguhan.

`PembinaCredentialsSeeder` menyiapkan login akun seeded yang masih pending. Nama lama `DevelopmentPembinaSeeder` tetap tersedia sebagai alias kompatibilitas. Form password hanya mensyaratkan nilai yang tidak kosong, tanpa aturan panjang/kompleksitas; alur ganti password pengguna tetap memeriksa password saat ini dan konfirmasi baru.


## GitHub dan hosting dengan git pull

Repository menyertakan source, migration/seeder, `composer.lock`, `package-lock.json`, `.env.example`, dan **hasil build `public/build`**. Dependensi, `.env` asli, cache, log, database lokal, upload privat, screenshot pengujian, dan `memory.md` diabaikan melalui `.gitignore`.

Setiap mengubah frontend, jalankan `npm ci` (jika dependensi berubah) lalu `npm run build` **sebelum commit/push**. Commit perubahan source beserta hasil build agar hosting tidak perlu Node.js.

Instalasi pertama di hosting:

1. Clone repository dan arahkan document root domain ke direktori `public`, bukan root proyek.
2. Jalankan `composer install --no-dev --prefer-dist --optimize-autoloader`.
3. Salin `.env.example` menjadi `.env` hanya pada instalasi pertama. Isi konfigurasi production: `APP_ENV=production`, `APP_DEBUG=false`, URL HTTPS, koneksi MySQL, dan layanan email.
4. Jalankan `php artisan key:generate` **hanya saat instalasi pertama**. Pertahankan APP_KEY dan `.env` tersebut pada update berikutnya.
5. Jalankan `php artisan migrate --force`. Jika membutuhkan data awal, jalankan `php artisan db:seed --force` satu kali sesuai kebutuhan. Password awal akun bawaan adalah `123456`.
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


## Foto laporan dari kamera

Tombol Ambil foto meminta kamera belakang melalui capture=environment pada browser mobile yang mendukung. Setiap foto diperkecil di perangkat (maksimal sisi panjang 1600 px) lalu dikonversi JPEG dengan target <=1.900.000 byte sebelum upload. Server membatasi foto baru maksimal 1953 KiB, sedikit di bawah 2.000.000 byte. Total maksimal 5 foto termasuk foto tersimpan. Foto lama tidak diubah. Browser tanpa dukungan capture dapat menampilkan pemilih berkas; pengujian kamera fisik Android/iPhone belum dilakukan. Push hasil build public/build agar modul kompresi terbaru tersedia di hosting.
