<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Roles use the existing users.role column and App\Models\User::ROLES.
        // No separate roles table or Spatie installation is needed.
    }
}
