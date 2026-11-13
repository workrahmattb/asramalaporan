<?php

namespace Database\Seeders;

use App\Models\Asrama;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AsramaSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (SimusSeedData::asramas() as $a) {
                Asrama::updateOrCreate(['code' => $a['code']], ['name' => $a['name'], 'gender' => $a['gender'], 'unit' => $a['unit'], 'is_active' => true]);
            }
        });
    }
}
