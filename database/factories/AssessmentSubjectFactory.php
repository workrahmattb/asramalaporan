<?php

namespace Database\Factories;

use App\Models\AssessmentSubject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentSubject>
 */
class AssessmentSubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(3, true),
            'indicator' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
