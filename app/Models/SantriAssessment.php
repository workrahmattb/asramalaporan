<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SantriAssessment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'version' => 'integer'];
    }

    /** @return BelongsTo<Santri,$this> */
    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    /** @return BelongsTo<AssessmentPeriod,$this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AssessmentPeriod::class, 'assessment_period_id');
    }

    /** @return BelongsTo<User,$this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User,$this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<SantriAssessmentItem,$this> */
    public function items(): HasMany
    {
        return $this->hasMany(SantriAssessmentItem::class)->orderBy('sort_order');
    }

    /**
     * @param  Builder<SantriAssessment>  $query
     * @return Builder<SantriAssessment>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->is_active) {
            return $query->whereRaw('1=0');
        }

        return $user->isAdmin() ? $query : $query->whereIn('asrama_id', $user->assignedAsramas()->select('asramas.id'));
    }

    public function getStatusLabelAttribute(): string
    {
        return ['draft' => 'Draft', 'submitted' => 'Dikirim', 'revision' => 'Perlu revisi', 'approved' => 'Disetujui'][$this->status] ?? $this->status;
    }
}
