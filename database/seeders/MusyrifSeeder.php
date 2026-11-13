<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MusyrifSeeder extends Seeder
{
    use SeedsPembina;

    public function run(): void
    {
        $this->seedPembina('putra', 'musyrif');
    }
}
