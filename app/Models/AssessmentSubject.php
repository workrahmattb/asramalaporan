<?php

namespace App\Models;

use Database\Factories\AssessmentSubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentSubject extends Model
{
    /** @use HasFactory<AssessmentSubjectFactory> */
    use HasFactory;

    protected $fillable = ['title', 'indicator', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
