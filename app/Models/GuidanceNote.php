<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidanceNote extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['note_date' => 'date'];
    }

    /** @return BelongsTo<User,$this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Santri,$this> */
    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    /** @return BelongsTo<Asrama,$this> */
    public function asrama(): BelongsTo
    {
        return $this->belongsTo(Asrama::class);
    }

    /**
     * @param  Builder<GuidanceNote>  $query
     * @return Builder<GuidanceNote>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->whereIn('asrama_id', $user->assignedAsramas()->select('asramas.id'));
    }

    public function getCategoryLabelAttribute(): string
    {
        return ['positive' => 'Perkembangan positif', 'guidance' => 'Perlu bimbingan', 'follow_up' => 'Tindak lanjut'][$this->category] ?? $this->category;
    }
}
