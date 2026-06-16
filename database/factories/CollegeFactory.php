<?php

namespace Database\Factories;

use App\Models\College;
use App\Models\UserAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class CollegeFactory extends Factory
{
    protected $model = College::class;

    public function definition()
    {
        return [
            'college_name' => $this->faker->unique()->company . ' College',
            'college_code' => $this->faker->unique()->regexify('[A-Z]{3,4}'),
            'description' => $this->faker->paragraph,
            'dean_id' => UserAccount::factory(), // Will be set later
            'contact_email' => $this->faker->companyEmail,
            'contact_phone' => $this->faker->phoneNumber,
        ];
    }
}
