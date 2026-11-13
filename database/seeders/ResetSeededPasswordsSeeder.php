<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetSeededPasswordsSeeder extends Seeder
{
    public function run(): void
    {
        $keys = [];
        foreach (SimusSeedData::asramas() as $group) {
            $keys = array_merge($keys, array_keys($group['people']));
        }
        DB::transaction(function () use ($keys) {
            foreach (User::where(fn ($q) => $q->whereIn('seed_key', $keys)->orWhere('email', 'admin@ppsyaaturrasul.com'))->lockForUpdate()->get() as $user) {
                $user->forceFill(['password' => Hash::make('123456'), 'credentials_ready' => true, 'must_change_password' => false, 'is_active' => true])->save();
            }
        });
    }
}
