<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property-read SantriPlacement|null $currentPlacement
 * @property Carbon|null $birth_date
 */
class Santri extends Model
{
    protected $guarded = [];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'birth_date' => 'date'];
    }

    /** @return HasMany<SantriPlacement,$this> */
    public function placements(): HasMany
    {
        return $this->hasMany(SantriPlacement::class);
    }

    /** @return HasOne<SantriPlacement,$this> */
    public function currentPlacement(): HasOne
    {
        return $this->hasOne(SantriPlacement::class)->whereDate('start_date', '<=', today())->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()))->latest('id');
    }

    /** @return HasMany<SantriAssessment,$this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(SantriAssessment::class);
    }

    /** @return HasMany<GuidanceNote,$this> */
    public function guidanceNotes(): HasMany
    {
        return $this->hasMany(GuidanceNote::class);
    }

    /**
     * @param  Builder<Santri>  $query
     * @return Builder<Santri>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_active) {
            return $query->whereRaw('1=0');
        }if ($user->isAdmin()) {
            return $query;
        }if (! $user->isPembina() && $user->role !== 'koordinator') {
            return $query->whereRaw('1=0');
        }

        return $query->whereHas('currentPlacement', fn ($q) => $q->whereIn('asrama_id', $user->assignedAsramas()->select('asramas.id')));
    }
}
