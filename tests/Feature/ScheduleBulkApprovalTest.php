<?php

use App\Http\Controllers\LoginController;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\UserAccount;

function bulkSchedule(string $status = 'pending'): Schedule
{
    return Schedule::create([
        'room_id' => Room::create(['room_name' => 'Bulk room', 'room_code' => uniqid('BULK-')])->id,
        'event_title' => 'CMSC 101', 'date' => '2026-10-15',
        'start_time' => '10:00', 'end_time' => '11:00', 'day_of_week' => 'Thursday', 'status' => $status,
    ]);
}

it('approves selected pending schedules and preserves other records without duplicate notifications', function () {
    $admin = UserAccount::factory()->create(['user_type' => 'admin', 'account_status' => 'active']);
    $this->actingAs($admin)->withSession(['user' => LoginController::sessionPayload($admin)]);
    $first = bulkSchedule();
    $second = bulkSchedule();
    $unselected = bulkSchedule();
    $closed = bulkSchedule('cancelled');
    $this->patchJson('/Schedule/bulk-approve', ['ids' => [$first->id, $second->id, $closed->id]])
        ->assertOk()->assertJsonPath('approved_count', 2);
    expect($first->fresh()->status)->toBe('approved')
        ->and($second->fresh()->status)->toBe('approved')
        ->and($unselected->fresh()->status)->toBe('pending')
        ->and($closed->fresh()->status)->toBe('cancelled');
    $count = \App\Models\ScheduleNotification::count();
    $this->patchJson('/Schedule/bulk-approve', ['ids' => [$first->id, $second->id]])
        ->assertOk()->assertJsonPath('approved_count', 0);
    expect(\App\Models\ScheduleNotification::count())->toBe($count);
});

it('rejects bulk approval for non admins', function () {
    $student = UserAccount::factory()->create(['user_type' => 'student', 'account_status' => 'active']);
    $schedule = bulkSchedule();
    $this->actingAs($student)->withSession(['user' => LoginController::sessionPayload($student)])
        ->patchJson('/Schedule/bulk-approve', ['ids' => [$schedule->id]])->assertForbidden();
    expect($schedule->fresh()->status)->toBe('pending');
});

it('validates the entire selection before applying approval', function () {
    $admin = UserAccount::factory()->create(['user_type' => 'admin', 'account_status' => 'active']);
    $schedule = bulkSchedule();
    $this->actingAs($admin)->withSession(['user' => LoginController::sessionPayload($admin)])
        ->patchJson('/Schedule/bulk-approve', ['ids' => [$schedule->id, 999999]])->assertUnprocessable();
    expect($schedule->fresh()->status)->toBe('pending');
});


it('keeps an approved booking and rejects conflicting pending schedules while preserving adjacent slots', function () {
    $admin = UserAccount::factory()->create(['user_type' => 'admin', 'account_status' => 'active']);
    $this->actingAs($admin)->withSession(['user' => LoginController::sessionPayload($admin)]);
    $winner = bulkSchedule('approved');
    $overlap = bulkSchedule();
    $overlap->update(['room_id' => $winner->room_id]);
    $adjacent = bulkSchedule();
    $adjacent->update(['room_id' => $winner->room_id, 'start_time' => '11:00', 'end_time' => '12:00']);
    $this->patchJson('/Schedule/'.$overlap->id.'/status', ['status' => 'approved'])
        ->assertOk()->assertJsonPath('schedule.status', 'rejected');
    expect($winner->fresh()->status)->toBe('approved')->and($adjacent->fresh()->status)->toBe('pending');
});

it('approves one selected schedule and automatically rejects its overlapping pending schedule', function () {
    $admin = UserAccount::factory()->create(['user_type' => 'admin', 'account_status' => 'active']);
    $this->actingAs($admin)->withSession(['user' => LoginController::sessionPayload($admin)]);
    $winner = bulkSchedule();
    $other = bulkSchedule();
    $other->update(['room_id' => $winner->room_id]);
    $this->patchJson('/Schedule/bulk-approve', ['ids' => [$winner->id, $other->id]])
        ->assertOk()->assertJsonPath('approved_count', 1)->assertJsonPath('rejected_count', 1);
    expect($winner->fresh()->status)->toBe('approved')->and($other->fresh()->status)->toBe('rejected');
});


it('approves all matching pending records beyond the first page in bounded chunks', function () {
    $admin = UserAccount::factory()->create(['user_type' => 'admin', 'account_status' => 'active']);
    $this->actingAs($admin)->withSession(['user' => LoginController::sessionPayload($admin)]);
    for ($i = 0; $i < 105; $i++) {
        bulkSchedule()->update(['event_title' => 'Matching workshop']);
    }
    $unmatched = bulkSchedule();
    $this->patchJson('/Schedule/bulk-approve', ['all' => true, 'search' => 'MATCHING WORKSHOP'])
        ->assertOk()->assertJsonPath('approved_count', 105);
    expect($unmatched->fresh()->status)->toBe('pending');
    expect(Schedule::where('status', 'approved')->count())->toBe(105);
});
