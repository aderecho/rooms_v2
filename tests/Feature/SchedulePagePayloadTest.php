<?php

use App\Http\Controllers\LoginController;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\UserAccount;
use Inertia\Testing\AssertableInertia as Assert;

it('keeps schedule page props compact without exposing full related account records', function () {
    $admin = UserAccount::factory()->create(['user_type' => 'admin', 'account_status' => 'active']);
    $faculty = UserAccount::factory()->create(['user_type' => 'faculty', 'address' => str_repeat('Long profile ', 1000)]);
    $room = Room::create(['room_name' => 'Payload room', 'room_code' => 'PAYLOAD-1', 'description' => str_repeat('Unused room data ', 1000)]);
    Schedule::create([
        'room_id' => $room->id, 'faculty_id' => $faculty->id, 'requester_id' => $faculty->id,
        'event_title' => 'Payload class', 'date' => '2026-10-15', 'start_time' => '10:00',
        'end_time' => '11:00', 'day_of_week' => 'Thursday', 'status' => 'pending',
    ]);
    $this->actingAs($admin)->withSession(['user' => LoginController::sessionPayload($admin)])
        ->get('/Schedule')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Schedule')->has('schedules', 1)
            ->where('schedules.0.room.room_name', 'Payload room')
            ->where('schedules.0.faculty.first_name', $faculty->first_name)
            ->missing('schedules.0.faculty.address')->missing('schedules.0.faculty.email')
            ->missing('schedules.0.requester.address')->missing('schedules.0.room.description')
            ->missing('schedules.0.term')->missing('faculty.0.address'));
});


it('renders only one page with 33395 schedules while preserving totals search and calendar pagination', function () {
    $admin = UserAccount::factory()->create(['user_type' => 'admin', 'account_status' => 'active']);
    $room = Room::create(['room_name' => 'Large dataset room', 'room_code' => 'LARGE-1']);
    $row = [
        'room_id' => $room->id, 'event_title' => 'Historical class', 'event_type' => 'class',
        'date' => '2026-10-15', 'start_time' => '10:00:00', 'end_time' => '11:00:00',
        'day_of_week' => 'Thursday', 'status' => 'pending',
    ];
    for ($inserted = 0; $inserted < 33395; $inserted += 250) {
        \Illuminate\Support\Facades\DB::table('schedules')->insert(array_fill(0, min(250, 33395 - $inserted), $row));
    }
    $this->actingAs($admin)->withSession(['user' => LoginController::sessionPayload($admin)]);
    $response = $this->get('/Schedule');
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('schedules', 10)->where('schedulePagination.total', 33395)
        ->where('scheduleCounts.pending', 33395)->where('matchingPendingCount', 33395));
    expect(strlen($response->getContent()))->toBeLessThan(100000);
    $this->get('/Schedule?page=2&per_page=50&search=HISTORICAL')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('schedules', 50)
            ->where('schedulePagination.current_page', 2)->where('schedulePagination.total', 33395));
    $this->getJson('/Schedule/calendar-data?start=2026-10-01&end=2026-10-31')
        ->assertOk()->assertJsonCount(500, 'data')->assertJsonPath('total', 33395)->assertJsonPath('last_page', 67);
    $this->getJson('/Schedule/calendar-data?start=2026-11-01&end=2026-11-30')
        ->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/Schedule?per_page=100000')->assertUnprocessable();
    $this->getJson('/Schedule/calendar-data?start=2026-01-01&end=2026-12-31')->assertUnprocessable();
});
