<?php

use App\Models\Room;
use App\Models\Schedule;
use App\Models\UserAccount;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('provides room codes and schedule allocation fields for the room usage grid', function () {
    $admin = UserAccount::create([
        'username' => 'room-usage-admin',
        'email' => 'room-usage@example.test',
        'password' => Hash::make('password'),
        'first_name' => 'Room',
        'last_name' => 'Usage',
        'user_type' => 'admin',
        'account_status' => 'active',
    ]);
    $room = Room::create([
        'room_name' => 'UG 216 Biology Lab',
        'room_code' => 'UG 216',
        'status' => 'available',
    ]);
    Schedule::create([
        'room_id' => $room->id,
        'event_title' => 'Communication Class',
        'event_type' => 'class',
        'course_code' => 'COMM 2',
        'section' => 'F',
        'date' => '2026-08-24',
        'start_time' => '09:30',
        'end_time' => '10:30',
        'day_of_week' => 'monday',
        'status' => 'approved',
    ]);

    $this->actingAs($admin)
        ->withSession(['user' => [
            'id' => $admin->id,
            'username' => $admin->username,
            'email' => $admin->email,
            'name' => $admin->full_name,
            'role' => $admin->user_type,
            'permissions' => [],
        ]])
        ->get(route('main.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MainDashboard')
            ->where('allRooms.0.room_code', 'UG 216')
            ->where('allRooms.0.schedules.0.room_id', $room->id)
            ->where('allRooms.0.schedules.0.course_code', 'COMM 2')
            ->where('allRooms.0.schedules.0.section', 'F')
            ->where('allRooms.0.schedules.0.date', '2026-08-24')
            ->where('allRooms.0.schedules.0.start_time', '09:30')
            ->where('allRooms.0.schedules.0.end_time', '10:30'));
});
