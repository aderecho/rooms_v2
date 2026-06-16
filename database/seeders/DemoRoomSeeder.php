<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\College;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\UserAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoRoomSeeder extends Seeder
{
    /**
     * Demo rooms shown in the schedule UI with equipment names and quantities.
     */
    public const DEMO_ROOMS = [
        'UG 114' => [
            'room_code' => 'UG-114',
            'equipments' => ['Chairs', 'Table', 'Remote Control'],
            'quantities' => [
                'Chairs' => 10,
                'Table' => 4,
                'Remote Control' => 2,
            ],
        ],
        'AVR 201' => [
            'room_code' => 'AVR-201',
            'equipments' => ['Chairs', 'Projector', 'Air Conditioner', 'Microphone'],
            'quantities' => [
                'Chairs' => 30,
                'Projector' => 1,
                'Air Conditioner' => 2,
                'Microphone' => 4,
            ],
        ],
        'Lab 305' => [
            'room_code' => 'LAB-305',
            'equipments' => ['Chairs', 'Table', 'Desktop Computer', 'Printer'],
            'quantities' => [
                'Chairs' => 20,
                'Table' => 8,
                'Desktop Computer' => 12,
                'Printer' => 2,
            ],
        ],
        'Main Conference Room' => [
            'room_code' => 'MCR-001',
            'equipments' => ['Chairs', 'Table', 'Projector', 'Whiteboard', 'Speaker System'],
            'quantities' => [
                'Chairs' => 40,
                'Table' => 6,
                'Projector' => 1,
                'Whiteboard' => 2,
                'Speaker System' => 1,
            ],
        ],
    ];

    public function run(): void
    {
        $building = Building::query()->first();
        $college = College::query()->first();
        $department = Department::query()->first();
        $roomType = RoomType::query()->first();
        $user = UserAccount::query()->first();

        if (!$building || !$college) {
            $this->command?->warn('DemoRoomSeeder skipped: missing building or college.');

            return;
        }

        foreach (self::DEMO_ROOMS as $roomName => $config) {
            $room = Room::query()->updateOrCreate(
                ['room_name' => $roomName],
                [
                    'room_code' => $config['room_code'],
                    'building_id' => $building->id,
                    'college_id' => $college->id,
                    'department_id' => $department?->id,
                    'room_type_id' => $roomType?->id,
                    'assigned_user_id' => $user?->id,
                    'floor_number' => 1,
                    'capacity' => 40,
                    'status' => 'available',
                    'equipments' => $config['equipments'],
                ]
            );

            foreach ($config['quantities'] as $equipmentName => $quantity) {
                $slug = Str::slug($equipmentName);
                $inventoryId = 'INV-DEMO-' . $room->id . '-' . $slug;

                Equipment::query()->updateOrCreate(
                    [
                        'room_id' => $room->id,
                        'equipment_name' => $equipmentName,
                    ],
                    [
                        'inventory_id' => $inventoryId,
                        'property_id' => 'PROP-DEMO-' . $room->id . '-' . $slug,
                        'description' => "Demo inventory for {$roomName}.",
                        'quantity' => $quantity,
                        'building_id' => $building->id,
                        'college_id' => $college->id,
                        'department_id' => $department?->id,
                        'status' => 'available',
                        'brand' => 'Demo',
                        'model' => 'DEMO-' . strtoupper($slug),
                        'serial_number' => 'SN-DEMO-' . $room->id . '-' . strtoupper($slug),
                        'assigned_user_id' => $user?->id,
                    ]
                );
            }
        }

        $this->command?->info('Demo rooms seeded: ' . count(self::DEMO_ROOMS) . ' rooms with equipment quantities.');
    }
}
