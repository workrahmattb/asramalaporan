<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MusyrifahSeeder extends Seeder
{
    use SeedsPembina;

    public function run(): void
    {
        $this->seedPembina('putri', 'musyrifah');
    }
}
