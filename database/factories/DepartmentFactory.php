<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\College;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition()
    {
        return [
            'department_name' => $this->faker->unique()->words(3, true) . ' Department',
            'department_code' => $this->faker->unique()->regexify('[A-Z]{2,3}'),
            'college_id' => College::factory(),
            'department_head_id' => null, // Will be set later
            'description' => $this->faker->paragraph,
            'office_location' => $this->faker->buildingNumber . ', ' . $this->faker->streetName,
            'contact_email' => $this->faker->companyEmail,
            'contact_phone' => $this->faker->phoneNumber,
        ];
    }
}
