<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    public function definition(): array
    {
        $roles = ['admin', 'user', 'moderator', 'viewer'];
        $departments = ['Computer Science', 'Engineering', 'Mathematics', 'Business'];
        $colleges = ['CCS', 'COE', 'CAS', 'CBA'];

        return [
            'username' => $this->faker->unique()->userName(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password123'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'role' => $this->faker->randomElement($roles),
            'department' => $this->faker->randomElement($departments),
            'college' => $this->faker->randomElement($colleges),
            'permissions' => $this->faker->randomElements(['read', 'write', 'delete', 'manage_users'], 2),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'permissions' => ['read', 'write', 'delete', 'manage_users'],
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'viewer',
            'permissions' => ['read'],
        ]);
    }
}
