<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SantriPlacement extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
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
}
