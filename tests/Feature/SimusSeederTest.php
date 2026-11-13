<?php

namespace Tests\Feature;

use App\Livewire\Admin\ManageMusyrifah;
use App\Models\Asrama;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\AsramaSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\MusyrifahSeeder;
use Database\Seeders\MusyrifSeeder;
use Database\Seeders\ResetSeededPasswordsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class SimusSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_counts_idempotency_and_preserved_credentials_history(): void
    {
        $this->seed();
        $this->assertSame(12, Asrama::where('gender', 'putra')->count());
        $this->assertSame(14, Asrama::where('gender', 'putri')->count());
        $this->assertDatabaseCount('users', 38);
        $this->assertDatabaseCount('asramas', 26);
        $this->assertDatabaseCount('asrama_user', 37);
        $this->assertSame(22, User::where('role', 'musyrif')->count());
        $this->assertSame(15, User::where('role', 'musyrifah')->count());
        $admin = User::where('role', 'super_admin')->firstOrFail();
        $this->assertTrue(Hash::check('123456', $admin->password));
        $u = User::where('seed_key', 'simus-musyrif-001')->firstOrFail();
        $this->assertSame('Ismail', $u->name);
        $this->assertTrue($u->credentials_ready);
        $this->assertTrue(Hash::check('123456', $u->password));
        $this->assertFalse($u->must_change_password);
        $this->assertSame('ismail@syafaaturrasul.com', $u->email);
        $admin->password = 'Changed-Admin-Password-99!';
        $admin->save();
        $adminHash = $admin->password;
        $u->email = 'ismail@example.com';
        $u->password = 'Personal-Password-99!';
        $u->credentials_ready = true;
        $u->save();
        $userHash = $u->password;
        DB::table('asrama_user')->where('user_id', $u->id)->update(['end_date' => today()->subDay()->toDateString()]);
        $history = DB::table('asrama_user')->orderBy('id')->get()->toJson();
        $this->seed();
        $this->assertDatabaseCount('users', 38);
        $this->assertDatabaseCount('asramas', 26);
        $this->assertDatabaseCount('asrama_user', 37);
        $this->assertSame($adminHash, $admin->fresh()->password);
        $this->assertSame($userHash, $u->fresh()->password);
        $this->assertSame('ismail@example.com', $u->fresh()->email);
        $this->assertSame($history, DB::table('asrama_user')->orderBy('id')->get()->toJson());
        $this->assertSame(2, Asrama::where('name', 'Aisyah 107 & 108')->firstOrFail()->users()->count());
        $this->assertTrue(User::where('name', "Jami'ul Ulum")->exists());
    }

    public function test_placeholder_accounts_block_login_and_account_mail_then_admin_can_provision(): void
    {
        $this->seed([AdminSeeder::class, AsramaSeeder::class, MusyrifSeeder::class, MusyrifahSeeder::class]);
        Notification::fake();
        $u = User::where('seed_key', 'simus-musyrif-001')->firstOrFail();
        $u->password = 'Known-Test-Password-99!';
        $u->save();
        $this->post('/login', ['email' => $u->email, 'password' => 'Known-Test-Password-99!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $u->sendPasswordResetNotification('token');
        $u->sendEmailVerificationNotification();
        Notification::assertNothingSent();
        $admin = User::where('role', 'super_admin')->firstOrFail();
        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('dashboard.admin'));
        foreach (['/dashboard/admin', '/admin/users', '/admin/asramas', '/admin/tasks', '/reports/monitoring', '/reports/monthly'] as $url) {
            $this->get($url)->assertOk();
        }
        Livewire::test(ManageMusyrifah::class)->call('edit', $u->id)->set('email', 'ismail@example.com')->set('password', 'New-Secure-Password-99!')->call('save')->assertHasNoErrors();
        $this->assertTrue($u->fresh()->credentials_ready);
        $this->assertTrue($u->fresh()->must_change_password);
        $this->actingAs($u->fresh())->get('/reports/create')->assertRedirect(route('password.first-change'));
        $this->get('/account/change-password')->assertOk();
        $this->post('/account/change-password', ['current_password' => 'New-Secure-Password-99!', 'password' => 'Final-Secure-Password-99!', 'password_confirmation' => 'Final-Secure-Password-99!'])->assertRedirect(route('dashboard'));
        $this->get('/reports/create')->assertOk();
        $this->get('/dashboard/musyrifah')->assertOk();
    }

    public function test_production_seed_uses_default_password_without_environment_configuration(): void
    {
        $this->app->instance('env', 'production');
        config(['simus.admin_password' => null]);
        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->run();
        $this->assertDatabaseCount('users', 38);
        foreach (User::all() as $user) {
            $this->assertTrue(Hash::check('123456', $user->password));
            $this->assertTrue($user->credentials_ready);
            $this->assertFalse($user->must_change_password);
            $this->assertStringNotContainsString('.invalid', $user->email);
        }
        $admin = User::where('role', 'super_admin')->firstOrFail();
        $this->actingAs($admin)->get('/admin/users')->assertOk();
        $admin->password = 'Existing-Personalized-Password-99!';
        $admin->save();
        $hash = $admin->password;
        $this->app->make(DatabaseSeeder::class)->setContainer($this->app)->run();
        $this->assertSame($hash, $admin->fresh()->password);
        $this->app->make(ResetSeededPasswordsSeeder::class)->run();
        $this->assertTrue(Hash::check('123456', $admin->fresh()->password));
    }

    public function test_development_accounts_can_login_without_password_rotation(): void
    {
        $this->seed();
        foreach (User::whereIn('role', ['musyrif', 'musyrifah'])->get() as $user) {
            $this->assertTrue(Hash::check('123456', $user->password));
            $this->assertTrue($user->credentials_ready);
            $this->assertFalse($user->must_change_password);
        }
        $this->post('/login', ['email' => 'ismail@syafaaturrasul.com', 'password' => '123456'])->assertRedirect(route('dashboard'));
        $this->get('/dashboard/musyrifah')->assertOk();
        $this->get('/reports/create')->assertOk();
        $hash = User::where('seed_key', 'simus-musyrif-001')->firstOrFail()->password;
        $this->seed();
        $this->assertSame($hash, User::where('seed_key', 'simus-musyrif-001')->firstOrFail()->password);
    }
}
