# Implementasi Seeder SIMUS

## File dibuat

- `database/seeders/RoleSeeder.php`
- `database/seeders/AdminSeeder.php`
- `database/seeders/AsramaSeeder.php`
- `database/seeders/MusyrifSeeder.php`
- `database/seeders/MusyrifahSeeder.php`
- `database/seeders/SimusSeedData.php` — katalog dengan kode kelompok dan identitas pengguna tetap.
- `database/seeders/SeedsPembina.php` — penyimpanan akun dan relasi tanpa menimpa histori.
- `database/migrations/2026_11_13_000002_add_seeder_identity_fields.php`
- `config/simus.php`
- `app/Http/Middleware/EnsureCredentialsEstablished.php`
- `app/Http/Controllers/FirstPasswordController.php`
- `resources/views/auth/first-password.blade.php`
- `tests/Feature/SimusSeederTest.php`

## File diubah

- `database/seeders/DatabaseSeeder.php` — urutan seeder, transaksi, dan 11 kegiatan tetap dipertahankan.
- `app/Models/User.php`, `app/Models/DailyReport.php`, `app/Policies/DailyReportPolicy.php` — role Musyrif/Super Admin dan akses data.
- `app/Providers/FortifyServiceProvider.php`, `app/Http/Middleware/EnsureActive.php`, `bootstrap/app.php` — login placeholder diblokir dan wajib penggantian password.
- `app/Services/DailyReportService.php`, `app/Services/ReportStatisticsService.php` — laporan dan rekap mendukung kedua jenis pembina.
- `app/Livewire/Dashboard/AdminDashboard.php`, `app/Livewire/Dashboard/MusyrifahDashboard.php` — akses dashboard role baru.
- `app/Livewire/Admin/ManageMusyrifah.php`, `app/Livewire/Admin/ManageAsrama.php`, `app/Livewire/Admin/ManageTasks.php` — akses super admin, pengaturan kredensial, gender dan filter.
- `app/Livewire/Reports/MonthlyRecap.php`, `app/Livewire/Reports/ReportHistory.php`, `app/Livewire/Reports/ReportMonitoring.php` — akses dan filter sesuai role baru.
- `app/Http/Controllers/ReportExportController.php`, `routes/web.php` — export super admin dan halaman pergantian password.
- `resources/views/layouts/simus.blade.php`
- `resources/views/livewire/admin/users.blade.php`, `resources/views/livewire/admin/asrama.blade.php`
- `resources/views/livewire/dashboard/index.blade.php`
- `resources/views/livewire/reports/form.blade.php`, `resources/views/livewire/reports/monthly.blade.php`
- `tests/Feature/ReportMonitoringTest.php` — pemeriksaan nama pada export menangani escaping XML.
- `.env.example`, `README.md` — konfigurasi production dan petunjuk aktivasi akun.
- `public/build/` — hasil rebuild aset.

## Hasil database lokal

`php artisan migrate` dan `php artisan db:seed` berhasil. Pemanggilan seed berulang menghasilkan jumlah yang sama:

| Data | Jumlah |
|---|---:|
| Super Admin | 1 |
| Asrama putra | 12 |
| Asrama putri | 14 |
| Total asrama | 26 |
| Musyrif | 22 |
| Musyrifah | 15 |
| Total pengguna | 38 |
| Penempatan aktif | 37 |
| Pembina menunggu penetapan kredensial | 37 |

Selisih dengan target 25 asrama berasal dari rincian sumber yang berisi 12 kelompok putra. Seluruh nama dipertahankan; tidak ada kelompok yang digabung atau dihapus. Perbandingan sebelum/sesudah reseed mengonfirmasi password, email, status penetapan kredensial, dan penempatan tidak berubah.

## Konfigurasi tersisa

- Admin perlu menetapkan email asli dan password individual pembina melalui menu Pengguna & Penempatan. Identifier `.invalid` tidak bisa login atau menerima notifikasi reset/verifikasi.
- Production perlu `SIMUS_ADMIN_PASSWORD` kuat dari environment. Akun yang baru dibuat pada production wajib mengganti password sebelum mengakses fitur. Reseeding tidak mereset password yang sudah ada.
- Konfigurasi layanan email dan validasi deployment MySQL/hosting tetap diperlukan sesuai README.


## Pembaruan development

Ditambahkan `database/seeders/DevelopmentPembinaSeeder.php`, dipanggil oleh `DatabaseSeeder` hanya untuk environment local/testing. Pada database development yang sudah diperbarui, 37 pembina sudah siap login memakai email berdasarkan nama `@syafaaturrasul.com` dan password `123456`; jumlah pembina menunggu kredensial menjadi 0. Akun admin tidak diubah. Password tetap di-hash, dan tidak wajib diganti saat login di development. Akun yang sudah dipersonalisasi tidak direset ketika seed diulang. Ketentuan placeholder dan penetapan kredensial pada bagian sebelumnya tetap berlaku untuk production.
