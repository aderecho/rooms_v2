<?php

use App\Models\Room;
use App\Models\Schedule;

function externalSchedulePayload(array $overrides = []): array
{
    return array_replace_recursive([
        'schedule' => [
            'event_title' => 'External System Booking',
            'event_type' => 'meeting',
            'date' => '2026-08-27',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'requester_name' => 'Partner System',
        ],
    ], $overrides);
}

it('creates a schedule using a room id', function () {
    $room = Room::create([
        'room_name' => 'API Room One',
        'room_code' => 'API-001',
        'status' => 'available',
    ]);

    $response = $this->postJson('/api/v1/room/schedule', externalSchedulePayload([
        'room_id' => $room->id,
    ]));

    $response
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('schedule.room_id', $room->id)
        ->assertJsonPath('schedule.status', 'pending')
        ->assertJsonPath('schedule.event_title', 'External System Booking');

    $this->assertDatabaseHas('schedules', [
        'room_id' => $room->id,
        'event_title' => 'External System Booking',
    ]);
});

it('creates a schedule using a room name', function () {
    $room = Room::create([
        'room_name' => 'API Room Two',
        'room_code' => 'API-002',
        'status' => 'available',
    ]);

    $this->postJson('/api/v1/room/schedule', externalSchedulePayload([
        'room_name' => $room->room_name,
    ]))
        ->assertCreated()
        ->assertJsonPath('schedule.room_id', $room->id);
});

it('creates every occurrence from a recurring schedule expression', function () {
    $room = Room::create([
        'room_name' => 'API Recurring Room',
        'room_code' => 'API-REC-001',
        'status' => 'available',
    ]);

    $this->postJson('/api/v1/room/schedule', [
        'room_name' => $room->room_name,
        'schedule' => [
            'event_title' => 'Recurring Class',
            'event_type' => 'class',
            'date' => 'T-TH from June to May 10:am-11:am 2026-2027',
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('schedule_count', 104)
        ->assertJsonCount(104, 'schedules');

    expect(Schedule::where('room_id', $room->id)->count())->toBe(104);
});

it('rejects an entire recurring request when one occurrence conflicts', function () {
    $room = Room::create([
        'room_name' => 'API Recurring Conflict Room',
        'room_code' => 'API-REC-002',
        'status' => 'available',
    ]);

    Schedule::create([
        'room_id' => $room->id,
        'event_title' => 'Existing Tuesday Class',
        'event_type' => 'class',
        'date' => '2026-06-02',
        'start_time' => '10:30',
        'end_time' => '11:30',
        'day_of_week' => 'Tuesday',
        'status' => 'approved',
    ]);

    $this->postJson('/api/v1/room/schedule', [
        'room_id' => $room->id,
        'schedule' => [
            'event_title' => 'Conflicting Recurring Class',
            'event_type' => 'class',
            'date' => 'T-TH from June to May 10:am-11:am 2026-2027',
        ],
    ])
        ->assertConflict()
        ->assertJsonPath('success', false)
        ->assertJsonPath('conflict.event_title', 'Existing Tuesday Class');

    expect(Schedule::where('room_id', $room->id)->count())->toBe(1);
});

it('rejects an overlapping schedule', function () {
    $room = Room::create([
        'room_name' => 'API Room Three',
        'room_code' => 'API-003',
        'status' => 'available',
    ]);

    Schedule::create([
        'room_id' => $room->id,
        'event_title' => 'Existing Booking',
        'event_type' => 'meeting',
        'date' => '2026-08-27',
        'start_time' => '09:30',
        'end_time' => '10:30',
        'day_of_week' => 'Thursday',
        'status' => 'approved',
    ]);

    $this->postJson('/api/v1/room/schedule', externalSchedulePayload([
        'room_id' => $room->id,
    ]))
        ->assertConflict()
        ->assertJsonPath('success', false)
        ->assertJsonPath('conflict.event_title', 'Existing Booking');

    expect(Schedule::where('room_id', $room->id)->count())->toBe(1);
});

it('requires one room identifier and valid schedule details', function () {
    $room = Room::create([
        'room_name' => 'API Room Four',
        'room_code' => 'API-004',
        'status' => 'available',
    ]);

    $this->postJson('/api/v1/room/schedule', externalSchedulePayload([
        'room_id' => $room->id,
        'room_name' => $room->room_name,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['room_id', 'room_name']);

    $this->postJson('/api/v1/room/schedule', externalSchedulePayload([
        'room_id' => $room->id,
        'schedule' => [
            'event_title' => '',
            'start_time' => '11:00',
            'end_time' => '10:00',
        ],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'schedule.event_title',
            'schedule.end_time',
        ]);
});
