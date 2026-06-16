<?php

namespace Database\Factories;

use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

class TermFactory extends Factory
{
    protected $model = Term::class;

    public function definition()
    {
        $termTypes = ['semester', 'trimester', 'quarter', 'summer', 'special'];
        $statuses = ['upcoming', 'active', 'completed', 'cancelled'];

        $startDate = $this->faker->dateTimeBetween('-1 year', '+1 year');
        $endDate = (clone $startDate)->modify('+4 months');
        $classesStart = (clone $startDate)->modify('+2 weeks');
        $classesEnd = (clone $endDate)->modify('-2 weeks');

        return [
            'term_name' => $this->faker->unique()->words(3, true) . ' Term',
            'term_code' => 'TERM-' . $this->faker->unique()->numberBetween(202300, 202500),
            'term_type' => $this->faker->randomElement($termTypes),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'enrollment_start' => (clone $startDate)->modify('-1 month')->format('Y-m-d'),
            'enrollment_end' => (clone $startDate)->modify('+1 week')->format('Y-m-d'),
            'classes_start' => $classesStart->format('Y-m-d'),
            'classes_end' => $classesEnd->format('Y-m-d'),
            'examination_start' => (clone $classesEnd)->modify('+1 week')->format('Y-m-d'),
            'examination_end' => (clone $classesEnd)->modify('+2 weeks')->format('Y-m-d'),
            'is_current' => $this->faker->boolean(30),
            'status' => $this->faker->randomElement($statuses),
            'academic_year' => $this->faker->numberBetween(2023, 2025),
            'notes' => $this->faker->optional()->sentence,
        ];
    }
}
