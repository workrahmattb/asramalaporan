<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property list<string> $rating_labels */
class SantriAssessmentItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['rating_labels' => 'array'];
    }

    /** @return BelongsTo<SantriAssessment,$this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(SantriAssessment::class, 'santri_assessment_id');
    }
}
