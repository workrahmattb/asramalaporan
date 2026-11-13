<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyTask extends Model
{
    protected $guarded = [];

    /** @return HasMany<DailyReportItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DailyReportItem::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
