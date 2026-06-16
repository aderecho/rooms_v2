<?php

namespace Database\Factories;

use App\Models\Equipment;
use App\Models\Room;
use App\Models\Building;
use App\Models\College;
use App\Models\Department;
use App\Models\UserAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipmentFactory extends Factory
{
    protected $model = Equipment::class;

    public function definition()
    {
        $statuses = ['available', 'in_use', 'maintenance', 'damaged', 'retired'];
        $equipmentTypes = [
            'Laptop', 'Desktop Computer', 'Projector', 'Printer', 'Scanner',
            'Microphone', 'Speaker System', 'Whiteboard', 'Television',
            'Camera', 'Tablet', 'Server', 'Router', 'Switch'
        ];

        $equipmentName = $this->faker->randomElement($equipmentTypes);

        return [
            'equipment_name' => $equipmentName,
            'inventory_id' => 'INV-' . $this->faker->unique()->numberBetween(10000, 99999),
            'property_id' => 'PROP-' . $this->faker->unique()->numberBetween(1000, 9999),
            'description' => $this->faker->optional()->sentence,
            'quantity' => $this->faker->numberBetween(1, 10),
            'room_id' => Room::factory(),
            'building_id' => Building::factory(),
            'college_id' => College::factory(),
            'department_id' => Department::factory(),
            'cfic_id' => 'CFIC-' . $this->faker->unique()->bothify('??##'),
            'status' => $this->faker->randomElement($statuses),
            'brand' => $this->faker->company,
            'model' => $this->faker->bothify('Model-??##'),
            'serial_number' => $this->faker->unique()->bothify('SN-########'),
            'purchase_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'purchase_price' => $this->faker->randomFloat(2, 1000, 50000),
            'assigned_user_id' => UserAccount::factory(),
            'specifications' => json_encode([
                'processor' => $this->faker->optional()->words(2, true),
                'ram' => $this->faker->optional()->randomElement(['4GB', '8GB', '16GB', '32GB']),
                'storage' => $this->faker->optional()->randomElement(['256GB SSD', '512GB SSD', '1TB HDD']),
                'warranty' => $this->faker->optional()->randomElement(['1 Year', '2 Years', '3 Years']),
            ]),
        ];
    }
}
