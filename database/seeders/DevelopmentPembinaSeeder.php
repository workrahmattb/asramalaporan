<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevelopmentPembinaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Seeder kredensial development hanya tersedia pada environment local/testing.');
        }

        DB::transaction(function () {
            foreach (SimusSeedData::asramas() as $group) {
                foreach ($group['people'] as $key => $name) {
                    $user = User::where('seed_key', $key)->lockForUpdate()->firstOrFail();
                    // Only initialize pending seeded accounts; preserve credentials already personalized.
                    if ($user->credentials_ready) {
                        continue;
                    }
                    $email = $user->email;
                    if (str_ends_with(strtolower($email), '.invalid')) {
                        $base = Str::slug(str_replace(["'", '’'], '', explode(',', $name, 2)[0]), '.');
                        $email = $base.'@syafaaturrasul.com';
                        $suffix = 2;
                        while (User::where('email', $email)->whereKeyNot($user->id)->exists()) {
                            $email = $base.'.'.$suffix++.'@syafaaturrasul.com';
                        }
                    }
                    $user->forceFill([
                        'email' => $email,
                        'email_verified_at' => now(),
                        'password' => Hash::make('123456'),
                        'credentials_ready' => true,
                        'must_change_password' => false,
                        'is_active' => true,
                    ])->save();
                }
            }
        });
    }
}
