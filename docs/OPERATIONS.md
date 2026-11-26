# Menjalankan, menguji, dan memperbarui Sistem Asrama

Terakhir diperiksa: 24 November 2026. Gunakan [AI_GUIDE.md](AI_GUIDE.md) untuk aturan fitur. Dokumen ini membedakan instalasi kosong dengan update hosting yang sudah berisi data.

## Sebelum menjalankan perintah

- Periksa direktori proyek, status Git, dan environment tujuan. Jangan menjalankan seeder/migrasi/reset hanya untuk mencoba UI.
- Jangan menampilkan isi `.env`, secret, dump database, foto privat, atau kredensial pengguna dalam keluaran kerja/dokumentasi.
- `memory.md`, konfigurasi IDE/MCP/skills lokal, database lokal dan runtime storage tidak ikut Git. `AGENTS.md`, README, docs, lock dependency, source dan `public/build` disiapkan untuk ikut Git.
- Source dapat mempunyai perubahan belum di-commit. Jangan memakai reset/clean/checkout massal untuk menyelesaikan tugas lain.

## Instalasi development baru dan kosong

1. `composer install` dan `npm ci` dari lock yang tersedia.
2. Hanya jika belum ada `.env`, salin `.env.example` dan isi konfigurasi yang sesuai. Hanya untuk instalasi baru, buat APP_KEY dengan `php artisan key:generate --no-interaction`.
3. Siapkan database development sendiri. MySQL memakai `DB_CONNECTION=mysql` dan parameter DB terkait. SQLite memerlukan file lokal yang sesuai konfigurasi.
4. Pada database kosong tersebut, `php artisan migrate --no-interaction`. Seed data awal hanya jika diperlukan; baca [SEEDER_IMPLEMENTATION.md](SEEDER_IMPLEMENTATION.md).
5. `npm run build`, kemudian `php artisan serve` atau jalankan workflow development yang sesuai `composer.json`.

Port 8002 pernah dipakai untuk proyek ini dan 8000 untuk proyek lain. Itu catatan sesi lokal, bukan konfigurasi wajib. Periksa proses/server aktif atau gunakan Boost get-absolute-url sebelum memberikan URL aplikasi; jangan menguji proyek lain karena port terlihat familiar.

## Pengujian dan pemeriksaan perubahan

`phpunit.xml` mengatur SQLite `:memory:`, cache/session/mail array, queue sync. Pastikan menggunakan environment pengujian ini, bukan database MySQL aplikasi aktif.

```sh
php artisan test --compact tests/Feature/AspectGradeFlowTest.php
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress --debug
npm run build
git diff --check
```

Ganti path tes menurut modul dalam [ARCHITECTURE.md](ARCHITECTURE.md). Pint wajib setelah perubahan PHP; tes baru diperlukan untuk perubahan logika bernilai regresi, bukan copy/layout semata. PHPStan `--debug` menghindari worker paralel yang dapat memerlukan socket di sandbox. Jalankan suite penuh `php artisan test --compact` bila cakupan perubahan memerlukannya; jangan menyatakan hasil pengujian dari sesi sebelumnya sebagai hasil sesi saat ini.

Boost merupakan dependency development. Pada komputer baru, pengaturan Boost/skills/MCP dapat dipasang sesuai kebutuhan lokal; file lokalnya tidak ikut repository. Jika `boost:install` atau `boost:update` meregenerasi AGENTS.md, pertahankan tautan pembuka ke panduan proyek dan review diff sebelum commit.

Untuk PDF, tes HTTP/header belum cukup: buat fixture tanpa menyimpan data ke database aplikasi, render PDF aktual, periksa halaman/gambar/tabel/catatan/tanda tangan, termasuk Putra/Putri serta konten panjang. Jangan mengecilkan/hilangnya isi untuk memaksa seluruh rapor selalu satu halaman.

Perubahan CSS/Blade frontend yang memengaruhi build perlu `npm run build`. Template dan CSS inline PDF tidak menggunakan bundle frontend. Warning opsional fontaine yang pernah muncul tidak berarti build gagal; periksa exit status dan hasilnya.

## Deployment hosting yang sudah berjalan

Alamat dan path yang dikonfirmasi pemilik:

- Source: `/home/ppsyafaa/asramalaporan`.
- Document root: `/home/ppsyafaa/public_html/asrama.ppsyafaaturrasul.com`.
- Domain: `asrama.ppsyafaaturrasul.com`.

Hosting memakai document root terpisah. CSS pernah hilang karena `public/build` source belum disalin ke direktori yang benar. Server hosting juga pernah gagal resolve domain yang salah; jangan menebak domain tanpa awalan `pp`.

### Sebelum update

1. Backup database lengkap, `.env`, dan `storage/app` ke lokasi privat di luar document root; pastikan backup tersedia dan dapat diunduh/dibaca.
2. Catat commit saat ini (`git rev-parse HEAD`) untuk rujukan pemulihan source.
3. Pastikan source, lock, logo dan hasil build terbaru sudah di-commit/push oleh pihak yang berwenang. Pembuatan kode/dokumentasi bukan berarti sudah push/deploy.

Jalankan satu per satu; hentikan bila gagal:

```sh
cd /home/ppsyafaa/asramalaporan
php artisan down --no-interaction
git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
rm -f public/hot
php artisan optimize:clear --no-interaction
php artisan migrate:status --no-interaction
```

Jika seluruh migration Ran, tidak perlu migrasi ulang. Bila Pending, tinjau source migration dan `php artisan migrate --pretend --force --no-interaction` sebelum menjalankan `php artisan migrate --force --no-interaction` dengan backup tersedia. `--pretend` memperlihatkan rencana SQL; bukan pengganti backup atau jaminan keberhasilan di schema hosting. Jangan rollback otomatis untuk memperbaiki error.

Setelah dependency/schema sudah benar:

```sh
mkdir -p /home/ppsyafaa/public_html/asrama.ppsyafaaturrasul.com/build
cp -a /home/ppsyafaa/asramalaporan/public/build/. /home/ppsyafaa/public_html/asrama.ppsyafaaturrasul.com/build/
php artisan optimize --no-interaction
php artisan up --no-interaction
```

Lakukan smoke check login, CSS, laporan/foto lama, santri/penempatan, simpan nilai, PDF per santri/satu asrama. Jika queue worker digunakan, restart melalui pengelola proses hosting. Jangan menghapus aset lama di document root saat halaman pengguna masih memakainya.

**Update rutin tidak boleh menjalankan:** `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, seeder, `composer run setup`, atau `key:generate`. Jangan menyalin `.env`/database/storage lokal menimpa hosting. Jangan mengubah APP_KEY lama. Jika gagal, pertahankan maintenance sambil memeriksa penyebab, bukan reset database.

## Persyaratan file, upload, dan PDF

- Composer production wajib setelah fitur PDF karena Dompdf berada di require, bukan require-dev. Gunakan install dari lock, bukan update dependency di hosting.
- PHP harus memenuhi lock serta ekstensi yang dibutuhkan, termasuk database/fileinfo/zip dan GD untuk pengolahan logo PNG oleh PDF. Periksa platform requirements pada server tujuan.
- `storage`, cache bootstrap dan direktori temporary PHP perlu writable. Foto laporan tetap privat; tidak perlu storage:link untuk membuka foto laporan.
- `upload_max_filesize` minimal 2M; `post_max_size` harus cukup untuk lima foto beserta payload request (lebih dari 10M pada panduan awal).
- Logo lokal dalam public source dibaca server dan di-embed ke PDF, tidak diminta melalui URL document root. Sertakan kedua logo dalam Git.
- Konfigurasi rapor: `config/rapor.php`, env opsional `RAPOR_CITY`, `RAPOR_HEAD_OF_CARE`, `RAPOR_DIRECTOR`. Setelah mengubah env/config, bangun ulang cache konfigurasi.
- PDF dibuat ulang per klik. Cetak satu asrama atau banyak pengguna bersamaan memakai CPU/RAM lebih besar. Kapasitas aktual hosting belum diukur; jangan menjanjikan batas aman berdasarkan tes lokal.

## Troubleshooting yang sudah pernah terjadi

| Gejala | Periksa dahulu |
|---|---|
| Halaman tanpa CSS setelah pull | public/hot, manifest dan assets source, salinan build di document root terpisah, alamat aset di browser |
| Cetak PDF gagal setelah update | Composer install dari lock, log aplikasi terbaru, ekstensi PHP, izin storage/temp, akun/asrama yang berwenang |
| Logo tidak tampil | Kedua file public, config kategori asrama, jalur public aplikasi; template memakai data PNG, jangan menggantinya dengan URL remote |
| Nilai gagal tersimpan karena berubah | Version rapor, periode aktif, status rapor, perubahan penempatan; muat ulang untuk menghindari overwrite |
| Santri gagal masuk asrama dalam batch | Santri aktif/belum ditempatkan/kategori sesuai, asrama aktif; batalkan pilihan stale lalu coba lagi |
| Rapor tak bisa diedit tetapi bisa dicetak | Perilaku benar: print tidak memerlukan persetujuan, edit tetap mengikuti status/versi/periode |

Tidak ada tindakan pada dokumen ini yang berarti izin menghapus data produksi atau mengirim pesan kepada pihak lain.
