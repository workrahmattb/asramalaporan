<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asrama extends Model
{
    protected $guarded = [];

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('assignment_role', 'start_date', 'end_date');
    }

    /** @return HasMany<DailyReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    /** @return HasMany<SantriPlacement,$this> */
    public function currentPlacements(): HasMany
    {
        return $this->hasMany(SantriPlacement::class)->whereDate('start_date', '<=', today())->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()));
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
