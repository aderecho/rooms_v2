<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CacheFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->word() . '_' . $this->faker->randomElement(['data', 'config', 'session']),
            'value' => json_encode($this->faker->words(5)),
            'expiration' => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiration' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function permanent(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiration' => null,
        ]);
    }
}
