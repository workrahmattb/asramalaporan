# Sistem Asrama — Monitoring Musyrif/Musyrifah

Aplikasi Laravel monolith untuk laporan tugas harian Pondok Pesantren Syafa'aturrasul. Menggunakan Laravel 13 / Livewire 4 yang sudah terpasang pada proyek awal (PRD menyebut Laravel 12), Tailwind, dan autentikasi Fortify. Struktur komponen mengikuti direktori `app/Livewire/Dashboard`, `Reports`, dan `Admin`.

## Untuk AI atau pengembang yang baru masuk

Mulai dari [AGENTS.md](AGENTS.md), lalu baca [Panduan AI](docs/AI_GUIDE.md), [Peta arsitektur dan data](docs/ARCHITECTURE.md), dan [Operasional/pengujian/deployment](docs/OPERATIONS.md). Dokumen tersebut memisahkan aturan bisnis yang berlaku sekarang dari riwayat perubahan. `memory.md` hanya catatan lokal, tidak ikut Git.

Panduan aktif terakhir ditinjau **24 November 2026**. Bagian deployment fitur lama di bawah adalah konteks instalasi historis; untuk hosting yang sudah berisi data, utamakan prosedur update aman dalam OPERATIONS.

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
- Master asrama, kegiatan, akun dan penempatan; seeder menyiapkan 11 kegiatan, 26 asrama, admin dan 37 pembina bawaan.
- Laporan draft dan terkirim, checklist opsional, kendala, catatan, maksimal 5 foto baru masing-masing di bawah 2 MB.
- Snapshot nama/urutan/daftar kegiatan, persentase server, indeks unik laporan dan item.
- Foto privat pada disk `local`, hanya tersedia melalui endpoint yang memeriksa policy.
- Riwayat dengan pagination dan filter bulan/tanggal/asrama/status.
- Verifikasi, permintaan revisi dengan catatan, admin membuka laporan terverifikasi; perubahan laporan/status tercatat di `report_audits`.
- Monitoring hari ini, daftar belum melapor, rekap bulanan berdasarkan riwayat penempatan sampai hari ini, rata-rata tanpa draft, dan export `.xlsx` dengan cakupan akses yang sama.
- Zona waktu Asia/Jakarta; validasi inti dalam Bahasa Indonesia.

Pelaporan tanggal lampau untuk laporan baru dibatasi ke admin pada implementasi awal. Laporan lama milik sendiri tetap dapat diedit selama belum diverifikasi. Form admin mendukung beberapa penempatan asrama per pembina/koordinator dengan riwayat tanggal.

## Validasi

```sh
php artisan test
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --debug --no-progress
npm run build
```

`--debug` menjalankan pemeriksaan tipe tanpa worker paralel yang memerlukan socket lokal. Tes memakai database SQLite in-memory terpisah.

## Deployment

Arahkan document root ke `public`, gunakan HTTPS, `APP_DEBUG=false`, set kredensial database dan layanan email untuk reset password, dan jalankan migrasi, build, serta `php artisan optimize`. PHP memerlukan ekstensi database, fileinfo, dan zip (export XLSX). Pastikan `storage` dan `bootstrap/cache` dapat ditulis. Tidak perlu `storage:link` untuk foto laporan privat. Atur `upload_max_filesize` minimal 2M dan `post_max_size` di atas 10M.

Backup database dan `storage/app/private` harus dijadwalkan pada hosting. Pemilik sudah menjalankan aplikasi di hosting. Konfigurasi email, hasil pengujian MySQL/MariaDB serta tampilan perangkat fisik perlu diverifikasi pada environment tujuan; tes lokal tidak membuktikan keadaan hosting. Notifikasi WhatsApp, PWA, penilaian berbobot, dan integrasi eksternal termasuk fase lanjutan PRD. Cetak rapor PDF langsung sudah tersedia.


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

Repository menyertakan source, migration/seeder, `composer.lock`, `package-lock.json`, `.env.example`, dan **hasil build `public/build`**. Dependensi, `.env` asli, cache, log, database lokal, upload privat, screenshot pengujian, dan `memory.md` diabaikan melalui `.gitignore`; `AGENTS.md` serta panduan AI disiapkan untuk ikut Git.

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


## Upload foto laporan

Foto dipilih dari galeri/berkas melalui upload Livewire biasa. Mode capture kamera dan kompresi JavaScript telah dinonaktifkan sesuai permintaan pengguna. Maksimal 5 foto termasuk foto tersimpan, masing-masing foto baru maksimal 1953 KiB (di bawah 2 MB). Foto tidak dikompres otomatis; pilih/perkecil foto sebelum mengunggah jika ukurannya terlalu besar. Preview dan hapus sebelum simpan tetap tersedia.

Riwayat/monitoring menampilkan Kendala dan Catatan tambahan. Pagination 10 laporan per halaman memakai Livewire; perpindahan detail dan menu tetap memakai navigasi SPA.

### Hosting aktif: document root terpisah

Pada hosting yang dipakai saat ini, source berada di `/home/ppsyafaa/asramalaporan`, sedangkan domain `asrama.ppsyafaaturrasul.com` melayani direktori `/home/ppsyafaa/public_html/asrama.ppsyafaaturrasul.com`.

Setelah pull perubahan yang menyertakan build frontend, salin aset ke document root yang sebenarnya:

```sh
cd /home/ppsyafaa/asramalaporan
git pull --ff-only
cp -a /home/ppsyafaa/asramalaporan/public/build/. /home/ppsyafaa/public_html/asrama.ppsyafaaturrasul.com/build/
php artisan optimize:clear
```

Langkah salin diperlukan selama document root terpisah dari `public` proyek. Pengguna sudah mengonfirmasi langkah tersebut memperbaiki CSS yang hilang. Tetap jalankan langkah Composer/migration pada panduan utama bila dependensi atau schema berubah.

## Pembinaan santri dan rapor berkala

Modul ini terpisah dari laporan kegiatan harian dan tidak mencakup absensi.

1. Admin membuka **Santri binaan**, menambahkan santri beserta penempatan asrama melalui popup. Hanya nama yang wajib. NISN, kelahiran, orang tua, kategori, dan asrama opsional; NISN tetap unik jika diisi. Penempatan harus sesuai kategori putra/putri; perubahan asrama mempertahankan riwayat penempatan.
2. Admin membuka **Tahun ajaran & semester**, menyiapkan master tahun ajaran, master aspek beserta indikator, lalu membuat semester Ganjil/Genap dengan memilih aspek dari master. Semester baru menggunakan pilihan A/B/C/D secara default. Status Aktif/Tidak aktif dikendalikan admin; tanggal menjadi keterangan, bukan pembuka/penutup otomatis. Mengaktifkan satu periode menonaktifkan periode lain. Aspek dan pilihan nilai dikunci setelah penilaian pertama tersimpan; gunakan periode baru untuk rubrik berbeda.
3. Musyrif/Musyrifah membuka tab **Santri** untuk melihat tabel identitas santri aktif dalam asrama penugasannya. Tombol **Rapor Asrama** di atas tabel membuka daftar pengisian nilai memakai periode aktif admin. Catatan perkembangan/bimbingan/tindak lanjut tetap tersedia pada profil santri. Pilih asrama dan satu aspek, isi nilai banyak santri dalam tabel, kemudian simpan seluruh nilai tersebut. Setelah semua aspek terisi, lengkapi catatan per santri melalui Catatan & kirim rapor. Semua aspek, perkembangan, dan fokus pembinaan harus terisi sebelum dikirim.
4. Koordinator memeriksa penilaian asrama penugasannya melalui **Pemeriksaan penilaian**. Admin dapat memeriksa seluruh asrama. Penilaian dapat disetujui atau dikembalikan dengan catatan revisi.
5. Rapor yang sudah tersimpan dapat dibuka langsung sebagai PDF A4 untuk dicetak atau diunduh. Admin dapat membukanya kembali untuk revisi dengan alasan wajib.

Ada satu penilaian per santri per periode, digunakan bersama oleh pembina yang berwenang. Pemeriksaan versi mencegah perubahan pembina lain tertimpa. Identitas, asrama, nama periode, aspek, dan pilihan nilai disalin saat penilaian pertama dibuat agar riwayat tetap konsisten. Tindakan simpan, pemeriksaan, dan buka kembali dicatat pada `assessment_audits`. Catatan pembinaan tidak otomatis menjadi nilai, skor, atau peringkat.

Akses santri mengikuti penempatan saat ini; akses catatan/rapor mengikuti asrama saat dicatat. Jika santri pindah asrama setelah penilaian periode yang sama dibuat, admin perlu menangani kelanjutan penilaian tersebut. Pembina tidak dapat mengubah penilaian di luar periode terbuka; admin dapat melanjutkan draft/revisi untuk koreksi administratif.

Daftar santri, catatan, riwayat penilaian, periode, dan pemeriksaan menggunakan pagination 10 baris. Navigasi internal memakai Livewire SPA, filter/pagination tanpa reload halaman. Cetak rapor dibuka di tab terpisah.

Pada deployment fitur ini, jalankan migrasi baru (tidak perlu mengulang seeder):

```sh
cd /home/ppsyafaa/asramalaporan
git pull --ff-only
php artisan migrate --force
cp -a /home/ppsyafaa/asramalaporan/public/build/. /home/ppsyafaa/public_html/asrama.ppsyafaaturrasul.com/build/
php artisan optimize:clear
```

Tidak ada santri/periode fiktif yang ditambahkan otomatis. Mulai dengan data santri dan periode yang diisi admin.

### Pembina dengan beberapa asrama

Pada popup **Pembina & penempatan**, centang seluruh asrama yang ditugaskan. Musyrif/Musyrifah dapat mengakses santri dari semua asrama tersebut. Form laporan baru menyediakan pilihan asrama; satu pembina dapat membuat satu laporan per asrama per tanggal. Asrama laporan tersimpan tidak berubah ketika profil penempatan diedit. Penempatan yang tetap dipilih mempertahankan tanggal mulai, sedangkan yang dihapus ditutup tanpa menghapus riwayat. Fitur ini memakai tabel penempatan yang sudah ada dan tidak membutuhkan migrasi tambahan.


### Identitas santri dan orang tua

Popup tambah/edit santri menyediakan NISN (pengganti NIS), tempat lahir, tanggal lahir, nama ayah/ibu, dan nomor kontak ayah/ibu. Hanya nama yang wajib; data lainnya boleh dilengkapi kemudian. Nomor kontak dan NISN disimpan sebagai teks untuk mempertahankan awalan nol atau tanda +. Tanggal lahir tidak boleh di masa depan. Data kelahiran dan orang tua ditampilkan pada profil santri sesuai hak akses penempatan.

Santri tanpa asrama hanya terlihat admin dan belum dapat menerima catatan/penilaian. Jika kategori dikosongkan saat asrama dipilih, kategori mengikuti asrama. Mengosongkan asrama saat edit menutup penempatan saat ini dan tetap mempertahankan riwayatnya.

Update ini memerlukan `php artisan migrate --force` pada hosting. Migrasi baru mengganti kolom NIS menjadi NISN (termasuk snapshot penilaian) tanpa menghapus nilai lama, menambah kolom identitas opsional, serta mengizinkan kategori kosong. Setelah pull, salin `public/build` ke document root seperti panduan deployment di atas.


### Alur menu Santri pembina

Daftar Santri berisi nama/NISN, asrama, TTL, nama ayah, dan nama ibu; pencarian serta filter asrama tetap tersedia. Pembina tidak memilih periode pada daftar/profil santri. Penilaian dipisahkan ke **Rapor Asrama** (`/santri/rapor-asrama`), dengan pilihan asrama/aspek untuk membuka tabel nilai, status kelengkapan tiap santri, serta Catatan & kirim rapor/Lihat rapor. Daftar identitas dan rapor memakai pagination 10 per halaman serta navigasi Livewire SPA.

Pengaturan tahun/semester global tersedia khusus admin pada Tahun Ajaran; master aspek ada di Aspek penilaian, dan rubrik semester melalui Aspek & skala nilai. Pilihan nilai dapat berupa label atau angka yang ditentukan admin (2–10 pilihan). Komponen yang sudah digunakan tetap dikunci untuk menjaga snapshot; buat periode baru untuk rubrik berbeda. Bila tidak ada periode aktif, identitas santri tetap dapat dilihat dan pengisian rapor ditutup. Adaptasi alur penilaian per aspek memerlukan migrasi master tahun ajaran/aspek dan indikator; lihat petunjuk berikut.

## Laravel Boost untuk development

Laravel Boost dipasang sebagai dependency development (`laravel/boost`, versi terkunci di `composer.lock`). Konfigurasi lokal Codex dibuat melalui `php artisan boost:install`: guidelines `AGENTS.md`, skills `.agents/skills`, dan server MCP `.codex/config.toml`. Konfigurasi MCP/skills lokal diabaikan Git; AGENTS.md kini disiapkan ikut Git sebagai pintu masuk dokumentasi AI. Konfigurasi pada komputer ini menggunakan path PHP dan artisan absolut agar server menunjuk proyek yang benar.

Untuk menyiapkan komputer development lain, jalankan `composer install`, lalu `php artisan boost:install` dan pilih Codex. Buka ulang proyek/chat Codex agar konfigurasi MCP yang baru dibaca. Untuk memperbarui guidelines/skills secara manual gunakan `php artisan boost:update`.

Boost tidak diperlukan di hosting production. Tetap gunakan `composer install --no-dev --prefer-dist --optimize-autoloader` di hosting; jangan menjalankan `boost:install` di server production. Tidak ada hook `boost:update` otomatis yang ditambahkan agar deployment tanpa dependency dev tetap berjalan. Boost hanya alat development; jangan menganggap pemasangannya mengubah alur bisnis tahun ajaran atau aspek.


## Penilaian per aspek mengikuti alur Rapor Asrama

Urutan setup admin pada **Tahun ajaran & semester**:

1. Pada menu Tahun Ajaran, tambahkan nama dan rentang tanggal tahun; Ganjil/Genap dibuat otomatis.
2. Tambahkan/edit master aspek dan indikator pada menu Aspek penilaian. Master awal otomatis diambil dari nama aspek periode lama saat migrasi; admin dapat melengkapi indikator.
3. Aktifkan tahun dan salah satu semester langsung di daftar Tahun Ajaran. Jika perlu, ubah rubrik lewat **Aspek & skala nilai**. Tahun ajaran hanya memiliki satu semester untuk setiap jenis; pengaktifan semester menonaktifkan semester lain.

Pembina membuka **Santri → Rapor Asrama**, memilih asrama serta aspek, lalu memilih **Isi nilai seluruh santri**. Khusus tabel input nilai per aspek, seluruh santri aktif pada asrama tampil sekaligus tanpa pagination. Tombol Simpan nilai menyimpan seluruh baris yang dapat diedit. Simpan nilai sebelum berpindah aspek. Nilai kosong menghapus nilai aspek tersebut pada santri yang sudah memiliki rapor; santri kosong yang belum pernah dinilai tidak dibuatkan draft otomatis. Nilai aspek lain dan catatan tetap tersimpan.

Semester baru menggunakan A/B/C/D secara default. Pilihan khusus masih dapat ditentukan admin sebelum penilaian dimulai. Periode lama mempertahankan skalanya; nilai lama tidak dikonversi menjadi huruf. Master dan indikator disalin ke semester, lalu ke item rapor saat draft dibuat. Perubahan master tidak mengubah semester/rapor lama.

Setelah aspek lengkap, pembina membuka **Catatan & kirim rapor**, mengisi perkembangan/fokus pembinaan, lalu mengirim untuk pemeriksaan. Nilai pada halaman catatan ditampilkan sebagai ringkasan; perubahan nilai pembina dilakukan dari tabel per aspek. Rapor terkirim/disetujui dikunci dari pengisian batch. Koordinator/admin dapat meminta revisi; admin dapat membuka kembali rapor disetujui dengan alasan.

Leger tersedia sebagai file Excel `.xlsx` per asrama/periode, termasuk status dan santri yang belum dinilai. Cetak gabungan per asrama memuat seluruh rapor tersimpan tanpa syarat persetujuan dan langsung menghasilkan satu PDF. Tidak ada absensi, skor otomatis, atau ranking.

Migrasi `2026_11_14_170909_add_rapor_masters_and_indicators` bersifat menambah data/kolom dan menghubungkan aspek lama ke master. Tahun ajaran periode lama dibiarkan kosong agar tidak menebak data historis; periode lama tetap dapat digunakan. Mengaktifkan tahun ajaran baru menutup periode dari tahun lain, termasuk periode lama tanpa tahun. Tidak perlu seed ulang.

Deployment setelah perubahan di-push:

```sh
cd /home/ppsyafaa/asramalaporan
git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
cp -a /home/ppsyafaa/asramalaporan/public/build/. /home/ppsyafaa/public_html/asrama.ppsyafaaturrasul.com/build/
php artisan optimize:clear
```


## Tahun Ajaran: satu daftar untuk seluruh aplikasi

Satu-satunya menu pengelolaan tahun/semester adalah **Tahun Ajaran** (`/admin/tahun-ajaran`). Alur mengikuti Rapor Asrama dengan tampilan Sistem Asrama:

1. Klik **Tambah tahun ajaran**, isi nama, tanggal mulai, dan tanggal selesai.
2. Sistem otomatis membuat **Ganjil dan Genap**, menyalin aspek master aktif, dengan skala awal A/B/C/D. Tidak perlu membuat semester satu per satu.
3. Klik **Aktifkan tahun**, lalu **Aktifkan semester** pada baris Ganjil atau Genap. Pengaturan berlaku global bagi semua pengguna dan tetap tersimpan setelah login ulang.
4. Edit data atau aktif/nonaktif langsung dari daftar yang sama. Tahun/semester baru selalu tidak aktif sampai diaktifkan admin.

Pilihan aspek semester tersedia lewat **Aspek & skala nilai**; popup tersebut hanya mengatur rubrik dan tidak membuat tahun/semester baru. Master aspek terpisah pada menu **Aspek penilaian** (`/admin/aspek-penilaian`), tanpa pengelolaan tahun ajaran di dalamnya. Form dropdown global berlapis dan panel kelola tahun yang duplikat sudah dihilangkan. Tautan lama `/admin/assessment-periods` diarahkan ke Tahun Ajaran.

Mengaktifkan tahun lain menonaktifkan semester lama. Menonaktifkan tahun menutup pengisian rapornya. Tombol Hapus hanya dapat menghapus tahun beserta semester yang belum memiliki nilai/rapor; tahun yang sudah digunakan harus dinonaktifkan agar riwayat tetap utuh. Tahun dan semester aktif tampil sebagai informasi baca saja di halaman pengguna. Riwayat penilaian lama tetap dapat dibuka dengan filter riwayat.

Migration baru `2026_11_14_192353_add_dates_to_academic_years` menambah tanggal pada tahun ajaran tanpa mengubah nilai lama. Hosting perlu `php artisan migrate --force`, sinkronkan `public/build` ke document root, lalu `php artisan optimize:clear`.

Pada penyesuaian lokal ini, satu periode legacy aktif yang kosong sudah dihapus sesuai permintaan pengguna. Tidak ada tahun ajaran aktif saat pemeriksaan dan tidak ada nilai/rapor yang dihapus. Pembersihan tersebut bukan bagian dari migration dan tidak dijalankan otomatis di hosting.

## Mengelola santri dari Asrama

Admin membuka **Asrama → Kelola santri** pada asrama tujuan. Halaman menampilkan anggota asrama beserta identitas ringkas dan jumlah anggota di daftar asrama.

Form **Tambah Santri ke Asrama** tampil langsung di atas tabel. Cari nama/NISN, centang satu atau beberapa santri aktif yang belum ditempatkan, periksa ringkasan nama/jumlah pilihan, lalu klik **Tambah Santri (jumlah)**. Pilihan bertahan saat mencari nama lain atau berpindah halaman kandidat. **Batal pilih** membatalkan satu nama, sedangkan **Batalkan semua** mengosongkan seluruh pilihan. Penyimpanan dilakukan dalam satu transaksi; jika salah satu pilihan sudah tidak memenuhi syarat, tidak ada santri dalam pilihan tersebut yang ditambahkan sampai masalah diperbaiki. Santri yang sudah berada di asrama lain tidak dipindahkan otomatis. Pembuatan identitas santri baru dilakukan dari menu **Santri binaan**.

Daftar anggota dan hasil pencarian kandidat memakai pagination 10 baris secara independen. Kategori putra/putri, status asrama/santri, dan penempatan diperiksa ulang di server. Fitur hanya untuk admin aktif. Anggota baru otomatis muncul di Santri binaan pembina yang ditugaskan ke asrama tersebut.

Tabel menyediakan tombol **Lihat** dan **Keluarkan**. Keluarkan membuka dialog konfirmasi; setelah dikonfirmasi, penempatan saat ini ditutup tanpa menghapus identitas santri, catatan, atau rapor. Santri yang sudah dikeluarkan dapat ditempatkan kembali; riwayat penempatan tetap tersimpan. Untuk pindah langsung ke asrama lain, gunakan Edit santri pada menu Santri binaan.

Tambahan fitur ini tidak mengubah schema database, tidak membutuhkan seeder/migrasi baru, dan tidak mereset anggota yang sudah ada. Navigasi tetap Livewire SPA dengan UI Kerja Asrama.

## Pagination daftar data

Seluruh daftar utama memakai pagination 10 data per halaman melalui Livewire: Pembina & Penempatan, Kegiatan harian, Asrama/anggota asrama, Santri, Tahun Ajaran, master aspek, riwayat/monitoring laporan, pemeriksaan/rapor, serta Rekap bulanan admin dan pembina. Khusus tabel input nilai per aspek Rapor Asrama, seluruh nama ditampilkan sekaligus tanpa pagination sesuai permintaan. Pilihan dropdown, checklist isian, rincian satu rapor, dan dokumen cetak tetap ditampilkan lengkap sesuai fungsinya.

Perubahan filter mengembalikan daftar ke halaman pertama. Membuka/menutup popup edit pembina atau kegiatan mempertahankan halaman yang sedang dibuka. Pagination rekap hanya membagi tampilan; perhitungan dan ekspor Excel tetap menggunakan seluruh data sesuai filter. Tidak ada perubahan schema, migration, atau seeder untuk tambahan pagination ini.

## Update hosting yang sudah berisi data

Pembaruan Kelola Santri Asrama dan pagination tanggal 15 November 2026 tidak menambah migration atau seeder. Data anggota lama dibaca dari penempatan yang sudah ada; tidak ada proses pemindahan atau penghapusan otomatis saat deployment.

Sebelum update, buat backup database melalui cPanel/phpMyAdmin (ekspor SQL lengkap) dan backup `.env` serta seluruh direktori `storage/app`. Simpan di luar document root dan pastikan file backup dapat dibaca/diunduh. Catat commit sebelum update dengan `git rev-parse HEAD`. Backup merupakan langkah pemulihan jika ada masalah; pengujian lokal tidak menggantikan backup hosting.

Setelah perubahan source dan `public/build` sudah di-commit dan di-push, jalankan di hosting **satu per satu**, hentikan jika ada perintah gagal:

```sh
cd /home/ppsyafaa/asramalaporan
php artisan down
git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader
rm -f public/hot
php artisan optimize:clear
php artisan migrate:status
```

Jika semua migration berstatus **Ran**, tidak perlu menjalankan migration lagi. Jika masih ada **Pending** dari pembaruan terdahulu, periksa dahulu `php artisan migrate --pretend --force`; pastikan backup tersedia sebelum menjalankan `php artisan migrate --force`. Jangan lanjut bila muncul error atau perubahan yang tidak dikenali.

Lanjutkan setelah pemeriksaan/migration berhasil:

```sh
mkdir -p /home/ppsyafaa/public_html/asrama.ppsyafaaturrasul.com/build
cp -a /home/ppsyafaa/asramalaporan/public/build/. /home/ppsyafaa/public_html/asrama.ppsyafaaturrasul.com/build/
php artisan optimize
php artisan up
```

Periksa login, CSS, laporan lama, foto, dan daftar santri/penempatan setelah update. Jika aplikasi memakai queue worker, restart worker melalui pengelola proses hosting setelah pembaruan. Jangan hapus aset lama di document root selama pembaruan agar halaman yang masih terbuka tidak kehilangan asetnya.

Untuk pembaruan ini **jangan** menjalankan `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`, seeder, `composer run setup`, atau `key:generate`. Jangan mengganti `.env`, APP_KEY, database, maupun storage dengan salinan lokal. Jika update gagal, biarkan maintenance aktif sambil memeriksa error; jangan mencoba reset database atau rollback migration sebagai perbaikan otomatis.

## Cetak rapor PDF langsung

Cetak per santri dan satu asrama menghasilkan `application/pdf` langsung di tab baru, menggunakan Dompdf (dependency production). Format A4 mengikuti contoh laporan bilingual: identitas tersimpan pada rapor, tabel No/Aspek/Indikator/Nilai, catatan dan fokus pembinaan, serta area tanda tangan. Nilai dan indikator tidak dihitung ulang dari master terbaru. Semua rapor tersimpan dapat dicetak tanpa persetujuan (draft, submitted, revision, approved); pembina tetap dibatasi asrama penugasannya, admin dapat mencetak seluruh asrama. PDF dikirim privat/no-store dan tidak disimpan di direktori publik.

Kota/nama penandatangan bawaan ada di `config/rapor.php`: Teluk Kuantan; Septian Kusuma Wijaya, A.Md (Kepala Pengasuhan); KH. Dr. Hamdani Purba, Lc., MA (Pimpinan Pondok). Pembina mengikuti akun pengisi rapor, Walisantri disediakan ruang tanda tangan. Tanggal mengikuti tanggal persetujuan jika tersedia, atau tanggal cetak untuk rapor yang belum disetujui. Jika diperlukan, sesuaikan lewat `.env` hosting: `RAPOR_CITY`, `RAPOR_HEAD_OF_CARE`, `RAPOR_DIRECTOR`, lalu bangun ulang cache konfigurasi.

Saat deploy fitur PDF, wajib sertakan `composer.json` dan `composer.lock`, jalankan `composer install --no-dev --prefer-dist --optimize-autoloader` setelah pull, lalu `php artisan optimize:clear` dan `php artisan optimize`. Composer memasang paket sesuai lock; jangan memakai composer update di hosting. Tidak perlu Chrome, Node, migration baru, seeder, atau mengubah data yang sudah tersimpan. Pastikan direktori storage dan direktori temporary PHP hosting writable. Uji satu rapor dan satu asrama setelah update. Sembilan aspek seperti contoh muat satu halaman; konten yang lebih panjang dapat berlanjut ke halaman berikutnya.

Kop rapor otomatis menampilkan Pondok Pesantren Syafa'aturrasul Putra atau Putri, dengan alamat Kuantan Singingi, Riau, berdasarkan kategori asrama pada `asrama_id` rapor (bukan penempatan santri saat ini). Jika kategori asrama kosong, digunakan nama pondok umum. Konfigurasi terpisah `rapor.institutions.putra` dan `rapor.institutions.putri` menyiapkan nama/alamat/logo. Logo resmi Putra dan Putri sudah tersedia di public/logo-pondok-putra.png dan public/logo-pondok-putri.png. Masing-masing dipilih melalui konfigurasi lalu ditanam sebagai data PNG di PDF, tanpa permintaan gambar ke domain hosting. Tidak ada placeholder gambar yang dicetak bila logo tidak tersedia.
