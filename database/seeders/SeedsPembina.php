<?php

namespace Database\Seeders;

use App\Models\Asrama;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait SeedsPembina
{
    private function seedPembina(string $gender, string $role): void
    {
        DB::transaction(function () use ($gender, $role) {
            foreach (SimusSeedData::asramas() as $group) {
                if ($group['gender'] !== $gender) {
                    continue;
                }
                $asrama = Asrama::where('code', $group['code'])->firstOrFail();
                foreach ($group['people'] as $key => $name) {
                    $user = User::where('seed_key', $key)->lockForUpdate()->first();
                    if (! $user) {
                        $email = $key.'@accounts.simus.invalid';
                        if (User::where('email', $email)->exists()) {
                            throw new \RuntimeException('Identifier internal seeder sudah dipakai akun lain.');
                        }
                        User::create(['name' => $name, 'email' => $email, 'password' => Hash::make(Str::random(64)), 'role' => $role, 'is_active' => true])->forceFill(['seed_key' => $key, 'credentials_ready' => false])->saveOrFail();
                        $user = User::where('seed_key', $key)->firstOrFail();
                    }
                    // Never overwrite credentials, identity updates, or historical/current placements.
                    if (! DB::table('asrama_user')->where('user_id', $user->id)->exists()) {
                        $user->asramas()->attach($asrama->id, ['assignment_role' => $role, 'start_date' => today()->toDateString(), 'end_date' => null]);
                    }
                }
            }
        });
    }
}
