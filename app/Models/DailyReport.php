<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property Carbon $report_date */
class DailyReport extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Asrama, $this> */
    public function asrama(): BelongsTo
    {
        return $this->belongsTo(Asrama::class);
    }

    /** @return HasMany<DailyReportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DailyReportItem::class);
    }

    /** @return HasMany<DailyReportPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(DailyReportPhoto::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return ['report_date' => 'date', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function getPercentageAttribute(): float
    {
        $items = $this->items;

        return $items->count() ? round($items->where('is_completed', true)->count() / $items->count() * 100, 2) : 0;
    }

    public function getStatusLabelAttribute(): string
    {
        return ['draft' => 'Draft', 'submitted' => 'Dikirim', 'verified' => 'Diverifikasi', 'revision' => 'Perlu Revisi'][$this->status] ?? $this->status;
    }

    /** @param Builder<DailyReport> $query
     * @return Builder<DailyReport> */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        } if ($user->isPembina()) {
            return $query->where('user_id', $user->id);
        }

        return $query->whereIn('asrama_id', $user->assignedAsramas()->select('asramas.id'));
    }
}
