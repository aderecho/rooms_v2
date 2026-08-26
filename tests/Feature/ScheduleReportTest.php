<?php

use App\Models\Room;
use App\Models\Schedule;
use App\Models\UserAccount;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function scheduleReportAdmin(): UserAccount
{
    return UserAccount::create([
        'username' => 'schedule-report-admin',
        'email' => 'schedule-report@example.test',
        'password' => Hash::make('password'),
        'first_name' => 'Schedule',
        'last_name' => 'Reporter',
        'user_type' => 'admin',
        'account_status' => 'active',
    ]);
}

it('renders the weekly rooms usage report in the reference page order', function () {
    $admin = scheduleReportAdmin();
    $rooms = collect(range(1, 4))->map(fn (int $number) => Room::create([
        'room_name' => "Avocado Room {$number}",
        'room_code' => "AVO-{$number}",
        'status' => 'available',
    ]));

    Schedule::create([
        'room_id' => $rooms[0]->id,
        'event_title' => 'Physical Education',
        'event_type' => 'class',
        'course_code' => 'PED 194',
        'section' => 'A',
        'date' => '2026-08-24',
        'start_time' => '13:00',
        'end_time' => '15:00',
        'day_of_week' => 'monday',
        'number_of_participants' => 38,
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
        ->get(route('reports.schedule', [
            'week_start' => '2026-08-26',
            'room_ids' => $rooms->pluck('id')->all(),
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ScheduleReport')
            ->where('weekStart', '2026-08-24')
            ->where('weekLabel', 'August 24, 2026 – August 28, 2026')
            ->where('pages.0.days.0.name', 'MONDAY')
            ->where('pages.0.days.1.name', 'THURSDAY')
            ->where('pages.1.days.0.name', 'TUESDAY')
            ->where('pages.1.days.1.name', 'FRIDAY')
            ->where('pages.2.days.0.name', 'WEDNESDAY')
            ->where('pages.0.days.0.rows.4.cells.0.text', 'PED 194 – A (38)')
            ->where('pages.0.days.0.rows.5.cells.0.text', 'PED 194 – A (38) until 3:00 PM')
            ->where('pages.0.days.0.rows.3.cells.1.text', 'LUNCH BREAK')
            ->where('pages.0.days.0.rows.0.cells.2.text', 'VACANT'));
});
