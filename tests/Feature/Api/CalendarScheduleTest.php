<?php

use App\Models\Room;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns schedules for the requested month in chronological order and supports a room filter', function () {
    $room = Room::create(['room_name' => 'Calendar Room', 'room_code' => 'CAL-1']);
    $other = Room::create(['room_name' => 'Other Room', 'room_code' => 'CAL-2']);
    foreach (['2028-02-29', '2028-01-31', '2028-03-01', '2028-02-01'] as $date) {
        Schedule::create([
            'room_id' => $room->id, 'event_title' => 'Meeting', 'event_type' => 'meeting',
            'date' => $date, 'start_time' => '09:00', 'end_time' => '10:00',
            'day_of_week' => strtolower(\Carbon\Carbon::parse($date)->englishDayOfWeek),
            'status' => 'approved', 'is_recurring' => true,
        ]);
    }
    Schedule::create([
        'room_id' => $other->id, 'event_title' => 'Other meeting', 'event_type' => 'meeting',
        'date' => '2028-02-01', 'start_time' => '08:00', 'end_time' => '09:00',
        'day_of_week' => 'tuesday', 'status' => 'pending',
    ]);
    $this->getJson('/api/v1/calendar/schedules?month=2028-02')
        ->assertOk()->assertJsonPath('meta.count', 3)
        ->assertJsonPath('data.0.room_id', $other->id)
        ->assertJsonPath('data.1.start_time', '09:00:00')
        ->assertJsonPath('data.2.date', '2028-02-29');
    $this->getJson('/api/v1/calendar/schedules?month=2028-02&room_id='.$room->id)
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.room_name', 'Calendar Room');
    $this->getJson('/api/v1/calendar/schedules?month=2028-04')
        ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.count', 0);
});

it('requires a valid year and month', function ($query) {
    $this->getJson('/api/v1/calendar/schedules'.$query)
        ->assertUnprocessable()->assertJsonValidationErrors('month');
})->with(['', '?month=2026-13', '?month=2026-02-01', '?month=invalid']);

it('returns the same complete equipment items in room and schedule APIs', function () {
    $equipment = [[
        'id' => '41',
        'name' => 'Projector',
        'inventory_count' => 2,
        'inventory' => [
            ['id' => 41, 'Item' => 'Projector', 'Property_number' => 'PROP-041', 'details' => ['serial' => 'ABC'], 'cost' => null],
            ['id' => 42, 'Item' => 'Projector', 'Property_number' => 'PROP-042', 'details' => ['serial' => 'DEF']],
        ],
    ]];
    $room = Room::create(['room_name' => 'Inventory Room', 'room_code' => 'INV-1', 'equipments' => $equipment]);
    $emptyRoom = Room::create(['room_name' => 'Empty Room', 'room_code' => 'INV-2']);
    foreach ([$room, $emptyRoom] as $index => $scheduledRoom) {
        Schedule::create([
            'room_id' => $scheduledRoom->id, 'event_title' => 'Meeting', 'event_type' => 'meeting',
            'date' => '2028-02-01', 'start_time' => $index ? '10:00' : '09:00', 'end_time' => '11:00',
            'day_of_week' => 'tuesday', 'status' => 'approved', 'equipment_needed' => ['Projector'],
        ]);
    }

    $rooms = $this->getJson('/api/v1/room/list')->assertOk()->json('data');
    expect(collect($rooms)->firstWhere('id', $room->id)['equipments'])->toBe($equipment);
    $this->getJson('/api/v1/room/list/'.$room->id)->assertOk()
        ->assertJsonPath('data.equipments', $equipment);
    $this->getJson('/api/v1/calendar/schedules?month=2028-02')->assertOk()
        ->assertJsonPath('data.0.equipments', $equipment)
        ->assertJsonPath('data.0.equipment_needed', ['Projector'])
        ->assertJsonPath('data.1.equipments', []);
    $this->getJson('/api/v1/calendar/schedules?month=2028-02&room_id='.$room->id)->assertOk()
        ->assertJsonPath('data.0.equipments', $equipment);

    $this->postJson('/api/v1/room/schedule', [
        'room_id' => $room->id,
        'schedule' => ['event_title' => 'New meeting', 'date' => '2028-02-02', 'start_time' => '09:00', 'end_time' => '10:00'],
    ])->assertCreated()
        ->assertJsonPath('schedule.room.equipments', $equipment)
        ->assertJsonPath('schedules.0.room.equipments', $equipment);
});
