<?php

namespace Database\Seeders;

use App\Models\DailyTask;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([RoleSeeder::class, AdminSeeder::class, AsramaSeeder::class, MusyrifSeeder::class, MusyrifahSeeder::class]);
            $this->call(PembinaCredentialsSeeder::class);
            foreach (['Membangunkan dan mengontrol salat Tahajud santri', 'Membimbing santri salat berjamaah Subuh', 'Membimbing santri salat berjamaah Asar', 'Membimbing santri salat berjamaah Magrib', 'Membimbing santri salat berjamaah Isya', 'Mengingatkan santri piket asrama', 'Melakukan absensi dan bimbingan santri menjelang tidur pukul 22.00', 'Menerima setoran Subuh sampai pukul 06.00', 'Membimbing santri berangkat ke madrasah pukul 06.00–07.00', 'Mengecek kebersihan dan kerapian kamar pukul 07.00–07.15', 'Mengadakan evaluasi adab harian setelah Al-Mulk'] as $i => $title) {
                DailyTask::firstOrCreate(['title' => $title], ['sort_order' => $i + 1, 'is_active' => true]);
            }
        });
    }
}
