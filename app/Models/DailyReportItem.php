<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReportItem extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<DailyReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class, 'daily_report_id');
    }

    /** @return BelongsTo<DailyTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(DailyTask::class, 'daily_task_id');
    }

    protected function casts(): array
    {
        return ['is_completed' => 'boolean'];
    }
}
