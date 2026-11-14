<?php

namespace App\Models;

use Database\Factories\AcademicYearFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 */
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use HasFactory;

    protected $fillable = ['name', 'is_active', 'start_date', 'end_date'];

    /** @return HasMany<AssessmentPeriod,$this> */
    public function semesters(): HasMany
    {
        return $this->hasMany(AssessmentPeriod::class)->orderByDesc('semester');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'start_date' => 'date', 'end_date' => 'date'];
    }
}
