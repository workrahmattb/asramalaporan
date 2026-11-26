# Seeder Sistem Asrama — kondisi saat ini

Diperbarui 24 November 2026 dari implementasi yang ada. Nama class `Simus*` adalah identifier teknis lama, bukan nama produk. Untuk gambaran proyek baca [AI_GUIDE.md](AI_GUIDE.md); untuk deployment baca [OPERATIONS.md](OPERATIONS.md).

## Fungsi dan urutan

`DatabaseSeeder` menjalankan satu transaksi dengan urutan:

1. RoleSeeder — menggunakan role pada kolom users, bukan tabel role/package permission baru.
2. AdminSeeder — akun bootstrap Administrator.
3. AsramaSeeder — master asrama dari SimusSeedData.
4. MusyrifSeeder dan MusyrifahSeeder — identitas pembina serta penempatan awal.
5. PembinaCredentialsSeeder — mengaktifkan kredensial akun katalog yang masih pending.
6. Sebelas DailyTask awal dengan firstOrCreate berdasarkan judul.

File rujukan berada di `database/seeders`. `SimusSeedData.php` adalah katalog nama/code/seed_key; `SeedsPembina.php` menjaga identitas stabil dan hanya membuat penempatan awal jika user belum memiliki riwayat penempatan.

## Data awal pada database kosong

| Data | Jumlah katalog |
|---|---:|
| Asrama putra | 12 |
| Asrama putri | 14 |
| Total asrama | 26 |
| Musyrif | 22 |
| Musyrifah | 15 |
| Pembina | 37 |
| Super admin bootstrap | 1 |
| Pengguna total | 38 |
| Kegiatan awal | 11 |

PRD awal menyebut total kelompok berbeda. Implementasi mempertahankan 12+14 rincian sumber, termasuk nama kelompok dengan `&`; jangan menggabungkannya untuk memaksakan total lama. Jumlah di atas bukan jumlah data terkini pada hosting.

Tidak ada santri, periode penilaian, atau nilai fiktif yang ditambahkan otomatis oleh seeder utama.

## Kredensial yang berlaku

- Admin bootstrap: `admin@ppsyaaturrasul.com`.
- Email pembina berdasarkan nama, domain `@syafaaturrasul.com`; suffix angka menangani benturan email. Mailbox sungguhan tidak dibuat oleh seeder.
- Password awal admin/pembina katalog adalah `123456`, termasuk production, sesuai permintaan pemilik; disimpan sebagai hash.
- Akun hasil seed siap login dan tidak diwajibkan mengganti password pertama. `SIMUS_ADMIN_PASSWORD` tidak diperlukan.
- Placeholder `.invalid` dan flag kesiapan masih ada untuk alur internal/legacy, tetapi akun baru dari seluruh rangkaian seed utama diteruskan ke PembinaCredentialsSeeder agar siap login.
- `DevelopmentPembinaSeeder` adalah alias kompatibilitas; bukan kebijakan production yang berbeda.

## Batas keamanan data saat reseed

Seeder bukan perintah update aplikasi rutin. Jangan menyimpulkan “idempotent” berarti tidak mengubah data bisnis:

- SeedsPembina tidak menimpa kredensial/identitas yang sudah dipersonalisasi atau menyusun ulang penempatan yang sudah memiliki histori.
- PembinaCredentialsSeeder hanya menginisialisasi akun katalog dengan credentials_ready false.
- AdminSeeder mempertahankan password akun bootstrap yang sudah ada, tetapi memperbarui nama/role/status dan flag sesuai implementasinya.
- **AsramaSeeder memperbarui nama/gender/unit/status aktif asrama katalog berdasarkan code.** Reseed dapat menimpa perubahan manual master asrama.
- ResetSeededPasswordsSeeder secara sengaja mengembalikan password akun bootstrap/katalog; jangan menjalankannya saat deployment rutin.
- Seeder tidak menggantikan backup; baca source dan tes sebelum mengubahnya.

Pada database development baru yang kosong, seed dapat dijalankan secara sengaja setelah migrate. Pada hosting yang sudah terisi, gunakan prosedur update tanpa seeder dalam OPERATIONS. Jangan menjalankan seed untuk memperbaiki CSS, PDF, pagination, atau menu.

## Verifikasi

`tests/Feature/SimusSeederTest.php` mencakup katalog, kredensial, pengulangan seed dan perlindungan identitas/penempatan. Jalankan tes tersebut jika mengubah seeder; gunakan database testing terpisah, bukan database aplikasi aktif.
