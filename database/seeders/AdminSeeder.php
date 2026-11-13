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
        $password = config('simus.admin_password');
        if (app()->isProduction() && (! is_string($password) || strlen($password) < 12 || ! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/[0-9]/', $password) || ! preg_match('/[^a-zA-Z0-9]/', $password))) {
            throw new \RuntimeException('Set SIMUS_ADMIN_PASSWORD yang kuat: minimal 12 karakter, huruf besar/kecil, angka, dan simbol.');
        }

        return is_string($password) && $password !== '' ? $password : '123456';
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
                $admin->forceFill(['email_verified_at' => now(), 'credentials_ready' => true, 'must_change_password' => app()->isProduction()])->save();
            } elseif (app()->isProduction() && Hash::check('123456', $existing->password)) {
                $admin->forceFill(['must_change_password' => true])->save();
            }
        });
    }
}
