<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property CarbonInterface|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    public const ROLES = ['super_admin', 'admin', 'koordinator', 'musyrif', 'musyrifah'];

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function isPembina(): bool
    {
        return in_array($this->role, ['musyrif', 'musyrifah'], true);
    }

    public function canReceiveAccountMail(): bool
    {
        return $this->credentials_ready && ! str_ends_with(strtolower($this->email), '.invalid');
    }

    public function sendPasswordResetNotification($token): void
    {
        if ($this->canReceiveAccountMail()) {
            $this->notify(new ResetPassword($token));
        }
    }

    public function sendEmailVerificationNotification(): void
    {
        if ($this->canReceiveAccountMail()) {
            $this->notify(new VerifyEmail);
        }
    }

    protected $attributes = ['role' => 'musyrifah', 'is_active' => true, 'credentials_ready' => true, 'must_change_password' => false];

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'credentials_ready' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Get the user's initials
     */
    /** @return BelongsToMany<Asrama, $this> */
    public function asramas(): BelongsToMany
    {
        return $this->belongsToMany(Asrama::class)->withPivot('assignment_role', 'start_date', 'end_date');
    }

    /** @return BelongsToMany<Asrama, $this> */
    public function assignedAsramas(?string $date = null): BelongsToMany
    {
        $date = $date ?: today()->toDateString();

        return $this->asramas()->wherePivot('assignment_role', $this->role)->whereDate('asrama_user.start_date', '<=', $date)->where(fn ($q) => $q->whereNull('asrama_user.end_date')->orWhereDate('asrama_user.end_date', '>=', $date));
    }

    /** @return HasMany<DailyReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
