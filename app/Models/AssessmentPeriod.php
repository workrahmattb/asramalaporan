<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property list<string> $rating_labels
 */
class AssessmentPeriod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'is_active' => 'boolean', 'rating_labels' => 'array'];
    }

    /** @return HasMany<AssessmentAspect,$this> */
    public function aspects(): HasMany
    {
        return $this->hasMany(AssessmentAspect::class)->orderBy('sort_order');
    }

    /** @return HasMany<SantriAssessment,$this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(SantriAssessment::class);
    }

    /** @return BelongsTo<AcademicYear,$this> */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function isOpen(): bool
    {
        return $this->is_active && (! $this->academic_year_id || $this->academicYear?->is_active === true);
    }

    /**
     * @param  Builder<AssessmentPeriod>  $query
     * @return Builder<AssessmentPeriod>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_active', true)->where(fn ($q) => $q->whereNull('academic_year_id')->orWhereHas('academicYear', fn ($year) => $year->where('is_active', true)));
    }
}
