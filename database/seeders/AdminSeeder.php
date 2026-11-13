<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public static function bootstrapPassword(): string
    {
        return '123456';
    }

    public function run(): void
    {
        DB::transaction(function () {
            $existing = User::where('email', 'admin@ppsyaaturrasul.com')->lockForUpdate()->first();
            $values = ['name' => 'Administrator', 'role' => 'super_admin', 'is_active' => true];
            if (! $existing) {
                $values['password'] = Hash::make(self::bootstrapPassword());
            }
            $admin = User::updateOrCreate(['email' => 'admin@ppsyaaturrasul.com'], $values);
            if (! $existing) {
                $admin->forceFill(['email_verified_at' => now(), 'credentials_ready' => true, 'must_change_password' => false])->save();
            } else {
                $admin->forceFill(['must_change_password' => false])->save();
            }
        });
    }
}
