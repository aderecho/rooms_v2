<?php

use App\Models\Room;
use App\Models\Schedule;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Schedule Import Feature Tests
|--------------------------------------------------------------------------
|
| Test #1 - Import Valid Schedule
| Purpose:
|   Ensures a valid schedule is successfully inserted into the database.
|
| Test #2 - Update Existing Schedule
| Purpose:
|   Ensures an existing schedule is updated when the incoming class_id
|   already exists instead of creating a duplicate record.
|
| Test #3 - Invalid Time Range
| Purpose:
|   Ensures schedules with an end_time earlier than or equal to start_time
|   are rejected by validation.
|
| Test #4 - Room Conflict
| Purpose:
|   Ensures overlapping schedules cannot be assigned to the same room.
|
| Test #5 - Faculty Conflict
| Purpose:
|   Ensures a faculty member cannot be assigned to multiple schedules that
|   overlap in time, even if the rooms are different.
|
| Test #6 - Duplicate Schedule
| Purpose:
|   Ensures an identical schedule cannot be imported twice, even when a
|   different class_id is provided.
|
|--------------------------------------------------------------------------
| Current Coverage
|--------------------------------------------------------------------------
|
| ✓ Valid schedule import
| ✓ Existing schedule update (class_id synchronization)
| ✓ Time range validation
| ✓ Room conflict detection
| ✓ Faculty conflict detection
| ✓ Duplicate schedule prevention
|
*/

test('can import a valid schedule', function () {

    /**
     * Arrange
     */
    $room = Room::factory()->create();

    $faculty = UserAccount::factory()->create([
        'user_type' => 'faculty',
    ]);

    $payload = [
        'schedules' => [
            [
                'class_id' => 1000001,
                'room_id' => $room->id,
                'faculty_id' => $faculty->id,
                'event_title' => 'Introduction to Programming',
                'event_type' => 'class',
                'course_code' => 'IT101',
                'course_name' => 'Introduction to Programming',
                'section' => 'BSIT-1A',
                'date' => '2026-07-13',
                'day_of_week' => 'Monday',
                'start_time' => '08:00:00',
                'end_time' => '09:30:00',
                'equipment_needed' => ['Projector'],
                'additional_requirements' => [],
                'status' => 'approved',
                'is_recurring' => false,
                'recurrence_pattern' => null,
            ]
        ]
    ];

    /**
     * Act
     */
    $response = $this->postJson('/api/v1/schedules/import', $payload);

    /**
     * Assert
     */
    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'summary' => [
            'received' => 1,
            'inserted' => 1,
            'updated' => 0,
            'failed' => 0,
        ],
    ]);

    $this->assertDatabaseHas('schedules', [
        'class_id' => 1000001,
    ]);

});

test('can update an existing schedule', function () {

    /**
     * Arrange
     */
    $room = Room::factory()->create();

    $faculty = UserAccount::factory()->create([
        'user_type' => 'faculty',
    ]);

    Schedule::factory()->create([
        'class_id' => 1000001,
        'room_id' => $room->id,
        'faculty_id' => $faculty->id,
        'event_title' => 'Introduction to Programming',
        'event_type' => 'class',
        'course_code' => 'IT101',
        'course_name' => 'Introduction to Programming',
        'section' => 'BSIT-1A',
        'date' => '2026-07-13',
        'day_of_week' => 'Monday',
        'start_time' => '08:00:00',
        'end_time' => '09:30:00',
        'status' => 'approved',
    ]);

    $payload = [
        'schedules' => [
            [
                'class_id' => 1000001,
                'room_id' => $room->id,
                'faculty_id' => $faculty->id,
                'event_title' => 'Introduction to Programming - UPDATED',
                'event_type' => 'class',
                'course_code' => 'IT101',
                'course_name' => 'Introduction to Programming',
                'section' => 'BSIT-1A',
                'date' => '2026-07-13',
                'day_of_week' => 'Monday',
                'start_time' => '10:00:00',
                'end_time' => '11:30:00',
                'equipment_needed' => ['Projector'],
                'additional_requirements' => [],
                'status' => 'approved',
                'is_recurring' => false,
                'recurrence_pattern' => null,
            ]
        ]
    ];

    /**
     * Act
     */
    $response = $this->postJson('/api/v1/schedules/import', $payload);

    /**
     * Assert
     */
    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'summary' => [
            'received' => 1,
            'inserted' => 0,
            'updated' => 1,
            'failed' => 0,
        ],
    ]);

    $this->assertDatabaseHas('schedules', [
        'class_id' => 1000001,
        'event_title' => 'Introduction to Programming - UPDATED',
        'start_time' => '10:00:00',
        'end_time' => '11:30:00',
    ]);

    $this->assertDatabaseCount('schedules', 1);

});

test('rejects a schedule with an invalid time range', function () {

    /**
     * Arrange
     */
    $room = Room::factory()->create();

    $faculty = UserAccount::factory()->create([
        'user_type' => 'faculty',
    ]);

    $payload = [
        'schedules' => [
            [
                'class_id' => 1000003,
                'room_id' => $room->id,
                'faculty_id' => $faculty->id,
                'event_title' => 'Invalid Time Test',
                'event_type' => 'class',
                'course_code' => 'IT101',
                'course_name' => 'Introduction to Programming',
                'section' => 'BSIT-1A',
                'date' => '2026-07-13',
                'day_of_week' => 'Monday',
                'start_time' => '10:00:00',
                'end_time' => '09:00:00', // Invalid
                'equipment_needed' => [],
                'additional_requirements' => [],
                'status' => 'approved',
                'is_recurring' => false,
                'recurrence_pattern' => null,
            ]
        ]
    ];

    /**
     * Act
     */
    $response = $this->postJson('/api/v1/schedules/import', $payload);

    /**
     * Assert
     */
    // $response->assertOk();
    $response->assertStatus(422);

    $response->assertJsonValidationErrors([
        'schedules.0.end_time',
    ]);

    $this->assertDatabaseMissing('schedules', [
        'class_id' => 1000003,
    ]);

});

test('rejects schedule when room is already occupied', function () {

    /**
     * Arrange
     */
    $room = Room::factory()->create();

    $faculty1 = UserAccount::factory()->create([
        'user_type' => 'faculty',
    ]);

    $faculty2 = UserAccount::factory()->create([
        'user_type' => 'faculty',
    ]);

    // Existing schedule occupying the room
    Schedule::factory()->create([
        'class_id' => 1000001,
        'room_id' => $room->id,
        'faculty_id' => $faculty1->id,
        'event_title' => 'Existing Class',
        'event_type' => 'class',
        'course_code' => 'IT101',
        'course_name' => 'Introduction to Programming',
        'section' => 'BSIT-1A',
        'date' => '2026-07-13',
        'day_of_week' => 'Monday',
        'start_time' => '08:00:00',
        'end_time' => '09:30:00',
        'status' => 'approved',
    ]);

    // New schedule overlaps the same room
    $payload = [
        'schedules' => [
            [
                'class_id' => 1000002,
                'room_id' => $room->id,
                'faculty_id' => $faculty2->id,
                'event_title' => 'New Class',
                'event_type' => 'class',
                'course_code' => 'IT102',
                'course_name' => 'Data Structures',
                'section' => 'BSIT-1B',
                'date' => '2026-07-13',
                'day_of_week' => 'Monday',
                'start_time' => '08:30:00',
                'end_time' => '10:00:00',
                'equipment_needed' => [],
                'additional_requirements' => [],
                'status' => 'approved',
                'is_recurring' => false,
                'recurrence_pattern' => null,
            ]
        ]
    ];

    /**
     * Act
     */
    $response = $this->postJson('/api/v1/schedules/import', $payload);

    /**
     * Assert
     */
    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'summary' => [
            'received' => 1,
            'inserted' => 0,
            'updated' => 0,
            'failed' => 1,
        ],
    ]);

    $response->assertJsonFragment([
        'reason' => 'Room is already occupied.',
    ]);

    $this->assertDatabaseMissing('schedules', [
        'class_id' => 1000002,
    ]);

    $this->assertDatabaseCount('schedules', 1);

});

test('rejects schedule when faculty is already assigned', function () {

    /**
     * Arrange
     */
    $room1 = Room::factory()->create();
    $room2 = Room::factory()->create();

    $faculty = UserAccount::factory()->create([
        'user_type' => 'faculty',
    ]);

    // Existing schedule
    Schedule::factory()->create([
        'class_id' => 1000001,
        'room_id' => $room1->id,
        'faculty_id' => $faculty->id,
        'event_title' => 'Existing Class',
        'event_type' => 'class',
        'course_code' => 'IT101',
        'course_name' => 'Introduction to Programming',
        'section' => 'BSIT-1A',
        'date' => '2026-07-13',
        'day_of_week' => 'Monday',
        'start_time' => '08:00:00',
        'end_time' => '09:30:00',
        'status' => 'approved',
    ]);

    // New schedule overlaps the same faculty
    $payload = [
        'schedules' => [
            [
                'class_id' => 1000002,
                'room_id' => $room2->id,   // Different room
                'faculty_id' => $faculty->id, // Same faculty
                'event_title' => 'Another Class',
                'event_type' => 'class',
                'course_code' => 'IT102',
                'course_name' => 'Data Structures',
                'section' => 'BSIT-1B',
                'date' => '2026-07-13',
                'day_of_week' => 'Monday',
                'start_time' => '08:30:00',
                'end_time' => '10:00:00',
                'equipment_needed' => [],
                'additional_requirements' => [],
                'status' => 'approved',
                'is_recurring' => false,
                'recurrence_pattern' => null,
            ]
        ]
    ];

    /**
     * Act
     */
    $response = $this->postJson('/api/v1/schedules/import', $payload);

    /**
     * Assert
     */
    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'summary' => [
            'received' => 1,
            'inserted' => 0,
            'updated' => 0,
            'failed' => 1,
        ],
    ]);

    $response->assertJsonFragment([
        'reason' => 'Faculty is already assigned to another schedule.',
    ]);

    $this->assertDatabaseMissing('schedules', [
        'class_id' => 1000002,
    ]);

    $this->assertDatabaseCount('schedules', 1);

});

test('rejects duplicate schedule', function () {

    /**
     * Arrange
     */
    $room = Room::factory()->create();

    $faculty = UserAccount::factory()->create([
        'user_type' => 'faculty',
    ]);

    Schedule::factory()->create([
        'class_id' => 1000001,
        'room_id' => $room->id,
        'faculty_id' => $faculty->id,
        'event_title' => 'Introduction to Programming',
        'event_type' => 'class',
        'course_code' => 'IT101',
        'course_name' => 'Introduction to Programming',
        'section' => 'BSIT-1A',
        'date' => '2026-07-13',
        'day_of_week' => 'Monday',
        'start_time' => '08:00:00',
        'end_time' => '09:30:00',
        'status' => 'approved',
    ]);

    $payload = [
        'schedules' => [
            [
                'class_id' => 9999999, // Different class_id
                'room_id' => $room->id,
                'faculty_id' => $faculty->id,
                'event_title' => 'Introduction to Programming',
                'event_type' => 'class',
                'course_code' => 'IT101',
                'course_name' => 'Introduction to Programming',
                'section' => 'BSIT-1A',
                'date' => '2026-07-13',
                'day_of_week' => 'Monday',
                'start_time' => '08:00:00',
                'end_time' => '09:30:00',
                'equipment_needed' => [],
                'additional_requirements' => [],
                'status' => 'approved',
                'is_recurring' => false,
                'recurrence_pattern' => null,
            ]
        ]
    ];

    /**
     * Act
     */
    $response = $this->postJson('/api/v1/schedules/import', $payload);

    /**
     * Assert
     */
    $response->assertOk();

    $response->assertJson([
        'success' => true,
        'summary' => [
            'received' => 1,
            'inserted' => 0,
            'updated' => 0,
            'failed' => 1,
        ],
    ]);

    $response->assertJsonFragment([
        'reason' => 'Duplicate schedule already exists.',
    ]);

    $this->assertDatabaseCount('schedules', 1);

});