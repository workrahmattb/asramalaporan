<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('simus:admin {email} {name}', function () {
    $password = $this->secret('Password admin (minimal 12 karakter)');
    if (strlen($password ?? '') < 12) {
        $this->error('Password minimal 12 karakter.');

        return 1;
    } if (User::where('email', $this->argument('email'))->exists()) {
        $this->error('Email sudah digunakan.');

        return 1;
    } User::create(['name' => $this->argument('name'), 'email' => $this->argument('email'), 'password' => $password, 'role' => 'admin', 'is_active' => true])->forceFill(['email_verified_at' => now()])->save();
    $this->info('Admin berhasil dibuat.');
})->purpose('Buat akun administrator Sistem Asrama');
