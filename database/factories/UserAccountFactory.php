<?php

namespace Database\Factories;

use App\Models\UserAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserAccountFactory extends Factory
{
    protected $model = UserAccount::class;

    public function definition()
    {
        return [
            'username' => $this->faker->unique()->userName,
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password123'), // Default password
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'middle_name' => $this->faker->optional()->firstName(),
            'employee_id' => $this->faker->optional()->bothify('EMP-#####'),
            'profile_picture' => $this->faker->optional()->imageUrl(200, 200, 'people'),
            'gender' => $this->faker->optional()->randomElement(['male', 'female', 'other']),
            'birth_date' => $this->faker->optional()->date(),
            'contact_number' => $this->faker->optional()->phoneNumber(),
            'address' => $this->faker->optional()->address(),
            'college_id' => \App\Models\College::inRandomOrder()->first()->id ?? null,
            'department_id' => \App\Models\Department::inRandomOrder()->first()->id ?? null,
            'user_type' => $this->faker->randomElement(['admin', 'faculty', 'staff', 'student', 'guest']),
            'roles' => json_encode($this->faker->optional()->randomElements(['admin', 'moderator', 'editor', 'viewer'], 2)),
            'account_status' => $this->faker->randomElement(['active', 'inactive', 'suspended', 'pending']),
            'last_login_at' => $this->faker->optional()->dateTime(),
            'last_login_ip' => $this->faker->optional()->ipv4(),
            'remember_token' => \Illuminate\Support\Str::random(10),
        ];
    }
}
