<?php

namespace Database\Factories;

use App\Models\Building;
use App\Models\College;
use Illuminate\Database\Eloquent\Factories\Factory;

class BuildingFactory extends Factory
{
    protected $model = Building::class;

    public function definition()
    {
        return [
            'building_name' => $this->faker->unique()->words(2, true) . ' Building',
            'address' => $this->faker->address,
            'description' => $this->faker->paragraph,
            'total_floors' => $this->faker->numberBetween(1, 10),
            'total_rooms' => $this->faker->numberBetween(5, 50),
            'has_elevator' => $this->faker->boolean(70),
            'has_parking' => $this->faker->boolean(80),
            'restroom_count' => $this->faker->numberBetween(1, 20),
            'ramp_count' => $this->faker->numberBetween(0, 5),
            'college_id' => College::factory(),
        ];
    }
}
