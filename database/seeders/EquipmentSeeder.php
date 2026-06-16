<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\College;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\UserAccount;
use Illuminate\Database\Seeder;

class EquipmentSeeder extends Seeder
{
    /**
     * Canonical equipment catalog used for room autocomplete suggestions.
     */
    public const CATALOG = [
        ['name' => 'TV', 'brand' => 'Samsung', 'status' => 'available', 'quantity' => 3],
        ['name' => 'Television', 'brand' => 'LG', 'status' => 'available', 'quantity' => 2],
        ['name' => 'Chairs', 'brand' => 'Generic', 'status' => 'available', 'quantity' => 10],
        ['name' => 'Remote Control', 'brand' => 'Universal', 'status' => 'available', 'quantity' => 2],
        ['name' => 'Air Conditioner', 'brand' => 'Carrier', 'status' => 'available', 'quantity' => 2],
        ['name' => 'Aircon', 'brand' => 'Daikin', 'status' => 'available', 'quantity' => 2],
        ['name' => 'Projector', 'brand' => 'Epson', 'status' => 'available', 'quantity' => 1],
        ['name' => 'Whiteboard', 'brand' => 'Office Pro', 'status' => 'available', 'quantity' => 2],
        ['name' => 'Microphone', 'brand' => 'Shure', 'status' => 'available', 'quantity' => 4],
        ['name' => 'Speaker System', 'brand' => 'JBL', 'status' => 'available', 'quantity' => 1],
        ['name' => 'Laptop', 'brand' => 'Dell', 'status' => 'available', 'quantity' => 5],
        ['name' => 'Desktop Computer', 'brand' => 'HP', 'status' => 'available', 'quantity' => 12],
        ['name' => 'Printer', 'brand' => 'Canon', 'status' => 'available', 'quantity' => 2],
        ['name' => 'Scanner', 'brand' => 'Fujitsu', 'status' => 'available', 'quantity' => 1],
        ['name' => 'Camera', 'brand' => 'Sony', 'status' => 'available', 'quantity' => 2],
        ['name' => 'Tablet', 'brand' => 'Apple', 'status' => 'available', 'quantity' => 3],
        ['name' => 'Router', 'brand' => 'Cisco', 'status' => 'available', 'quantity' => 2],
        ['name' => 'Switch', 'brand' => 'Netgear', 'status' => 'available', 'quantity' => 2],
        ['name' => 'Server', 'brand' => 'Dell', 'status' => 'maintenance', 'quantity' => 1],
        ['name' => 'Desk', 'brand' => 'IKEA', 'status' => 'available', 'quantity' => 8],
        ['name' => 'Table', 'brand' => 'IKEA', 'status' => 'available', 'quantity' => 4],
        ['name' => 'Clock', 'brand' => 'Seiko', 'status' => 'available', 'quantity' => 1],
        ['name' => 'Fan', 'brand' => 'Panasonic', 'status' => 'available', 'quantity' => 4],
        ['name' => 'Keyboard', 'brand' => 'Logitech', 'status' => 'available', 'quantity' => 10],
        ['name' => 'Mouse', 'brand' => 'Logitech', 'status' => 'available', 'quantity' => 10],
    ];

    public function run(): void
    {
        $building = Building::query()->first();
        $college = College::query()->first();
        $department = Department::query()->first();
        $user = UserAccount::query()->first();

        $inventoryBase = Equipment::query()->max('id') ?? 0;

        foreach (self::CATALOG as $index => $item) {
            $inventoryNumber = $inventoryBase + $index + 1;

            Equipment::updateOrCreate(
                ['inventory_id' => 'INV-CAT-' . str_pad((string) $inventoryNumber, 5, '0', STR_PAD_LEFT)],
                [
                    'equipment_name' => $item['name'],
                    'property_id' => 'PROP-CAT-' . str_pad((string) $inventoryNumber, 5, '0', STR_PAD_LEFT),
                    'description' => 'Catalog item for room equipment picker.',
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'room_id' => null,
                    'building_id' => $building?->id,
                    'college_id' => $college?->id,
                    'department_id' => $department?->id,
                    'status' => $item['status'],
                    'brand' => $item['brand'],
                    'model' => 'CAT-' . ($index + 1),
                    'serial_number' => 'SN-CAT-' . str_pad((string) $inventoryNumber, 6, '0', STR_PAD_LEFT),
                    'assigned_user_id' => $user?->id,
                ]
            );
        }

        $this->command?->info('Equipment catalog seeded: ' . count(self::CATALOG) . ' items.');
    }
}
