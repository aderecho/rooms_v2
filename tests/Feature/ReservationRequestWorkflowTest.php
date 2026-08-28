<?php

use App\Http\Controllers\LoginController;
use App\Models\ReservationRequest;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleNotification;
use App\Models\UserAccount;
use App\Notifications\ReservationRequestMailNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow('2026-08-27 09:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function reservationUser(string $type, string $suffix): UserAccount
{
    return UserAccount::create([
        'username' => "{$type}-{$suffix}",
        'email' => "{$type}-{$suffix}@example.test",
        'password' => Hash::make('password'),
        'first_name' => ucfirst($type),
        'last_name' => ucfirst($suffix),
        'user_type' => $type,
        'account_status' => 'active',
    ]);
}

function reservationRoom(string $suffix = 'one', int $capacity = 40): Room
{
    return Room::create([
        'room_name' => "Reservation Room {$suffix}",
        'room_code' => 'RES-'.strtoupper($suffix),
        'capacity' => $capacity,
        'status' => 'available',
    ]);
}

function reservationSession(UserAccount $user): array
{
    return ['user' => LoginController::sessionPayload($user)];
}

function reservationPayload(Room $room, array $overrides = []): array
{
    return array_merge([
        'room_id' => $room->id,
        'reservation_date' => '2026-09-10',
        'start_time' => '09:00',
        'end_time' => '10:00',
        'purpose' => 'Student organization planning meeting',
        'attendees' => 20,
        'remarks' => 'Please arrange the chairs in a circle.',
    ], $overrides);
}

it('allows an authenticated student to submit a pending request and notifies active admins', function () {
    Notification::fake();
    $student = reservationUser('student', 'submitter');
    $admin = reservationUser('admin', 'reviewer');
    $room = reservationRoom();

    $this->actingAs($student)
        ->withSession(reservationSession($student))
        ->post(route('student.reservations.store'), reservationPayload($room))
        ->assertRedirect();

    $request = ReservationRequest::firstOrFail();
    expect($request->status)->toBe('pending')
        ->and($request->student_id)->toBe($student->id)
        ->and($request->room_id)->toBe($room->id);

    $this->assertDatabaseHas('schedule_notifications', [
        'user_id' => $admin->id,
        'reservation_request_id' => $request->id,
        'type' => 'reservation_submitted',
    ]);
    $databaseNotification = ScheduleNotification::where('user_id', $admin->id)->firstOrFail();
    $this->actingAs($admin)
        ->withSession(reservationSession($admin))
        ->patch(route('schedule-notifications.read', $databaseNotification))
        ->assertOk();
    expect($databaseNotification->fresh()->read_at)->not->toBeNull();
    Notification::assertSentTo(
        $admin,
        ReservationRequestMailNotification::class,
        fn ($notification) => $notification->event === 'submitted',
    );
});

it('builds queued email notifications with reservation details and role-correct links', function () {
    $student = reservationUser('student', 'mail-student');
    $admin = reservationUser('admin', 'mail-admin');
    $request = ReservationRequest::factory()->create([
        'student_id' => $student->id,
        'room_id' => reservationRoom('mail')->id,
        'purpose' => 'Thesis presentation rehearsal',
    ])->load(['student', 'room']);

    $adminMail = (new ReservationRequestMailNotification($request, 'submitted'))->toMail($admin);
    $adminNotification = new ReservationRequestMailNotification($request, 'submitted');
    expect($adminMail->subject)->toContain('New reservation request')
        ->and($adminMail->actionUrl)->toContain("/ReservationRequests/{$request->id}")
        ->and(implode(' ', $adminMail->introLines))->toContain('Thesis presentation rehearsal')
        ->and($adminNotification->queue)->toBe('mail');

    $request->update([
        'status' => 'rejected',
        'admin_response' => 'Please select a room with presentation equipment.',
        'rejected_at' => now(),
    ]);
    $studentMail = (new ReservationRequestMailNotification($request->fresh(['student', 'room']), 'rejected'))->toMail($student);
    expect($studentMail->subject)->toContain('Reservation rejected')
        ->and($studentMail->actionUrl)->toContain("/MyReservations/{$request->id}")
        ->and(implode(' ', $studentMail->introLines))->toContain('presentation equipment');
});

it('returns only truly available rooms and rejects schedule and request overlaps', function () {
    $student = reservationUser('student', 'availability');
    $availableRoom = reservationRoom('available');
    $scheduledRoom = reservationRoom('scheduled');
    $requestedRoom = reservationRoom('requested');

    Schedule::create([
        'room_id' => $scheduledRoom->id,
        'event_title' => 'Approved class',
        'event_type' => 'class',
        'date' => '2026-09-10',
        'start_time' => '09:30',
        'end_time' => '10:30',
        'day_of_week' => 'Thursday',
        'status' => 'approved',
    ]);
    ReservationRequest::factory()->create([
        'student_id' => reservationUser('student', 'other')->id,
        'room_id' => $requestedRoom->id,
        'reservation_date' => '2026-09-10',
        'start_time' => '09:15',
        'end_time' => '09:45',
    ]);

    $this->actingAs($student)
        ->withSession(reservationSession($student))
        ->getJson(route('student.reservations.availability', [
            'reservation_date' => '2026-09-10',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'attendees' => 20,
        ]))
        ->assertOk()
        ->assertJsonPath('available_count', 1)
        ->assertJsonFragment(['id' => $availableRoom->id, 'is_available' => true])
        ->assertJsonFragment(['id' => $scheduledRoom->id, 'is_available' => false])
        ->assertJsonFragment(['id' => $requestedRoom->id, 'is_available' => false]);

    $this->actingAs($student)
        ->withSession(reservationSession($student))
        ->post(route('student.reservations.store'), reservationPayload($scheduledRoom))
        ->assertSessionHasErrors('room_id');
});

it('validates date, time range, operating hours, capacity, and student overlaps', function () {
    $student = reservationUser('student', 'validation');
    $room = reservationRoom('capacity', 10);

    $this->actingAs($student)
        ->withSession(reservationSession($student))
        ->post(route('student.reservations.store'), reservationPayload($room, [
            'reservation_date' => '2026-08-26',
            'start_time' => '10:00',
            'end_time' => '09:00',
            'attendees' => 20,
        ]))
        ->assertSessionHasErrors(['reservation_date', 'end_time']);

    $this->actingAs($student)
        ->withSession(reservationSession($student))
        ->post(route('student.reservations.store'), reservationPayload($room, [
            'start_time' => '06:00',
            'end_time' => '07:00',
        ]))
        ->assertSessionHasErrors('start_time');

    $this->actingAs($student)
        ->withSession(reservationSession($student))
        ->post(route('student.reservations.store'), reservationPayload($room, ['attendees' => 11]))
        ->assertSessionHasErrors('attendees');

    ReservationRequest::factory()->create([
        'student_id' => $student->id,
        'room_id' => $room->id,
        'reservation_date' => '2026-09-10',
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);
    $otherRoom = reservationRoom('other');
    $this->actingAs($student)
        ->withSession(reservationSession($student))
        ->post(route('student.reservations.store'), reservationPayload($otherRoom, [
            'start_time' => '09:30',
            'end_time' => '10:30',
        ]))
        ->assertSessionHasErrors('start_time');
});

it('prevents a student from viewing another students request', function () {
    $owner = reservationUser('student', 'owner');
    $other = reservationUser('student', 'intruder');
    $request = ReservationRequest::factory()->create([
        'student_id' => $owner->id,
        'room_id' => reservationRoom('ownership')->id,
    ]);

    $this->actingAs($other)
        ->withSession(reservationSession($other))
        ->get(route('student.reservations.show', $request))
        ->assertForbidden();

    $this->actingAs($owner)
        ->withSession(reservationSession($owner))
        ->get(route('student.reservations.show', $request))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ReservationRequestDetail')
            ->where('viewerMode', 'student')
            ->where('reservationRequest.data.id', $request->id));
});

it('protects the admin list and decision actions from non-admin users', function () {
    $student = reservationUser('student', 'not-admin');
    $request = ReservationRequest::factory()->create([
        'student_id' => $student->id,
        'room_id' => reservationRoom('authorization')->id,
    ]);

    $this->actingAs($student)
        ->withSession(reservationSession($student))
        ->get(route('admin.reservation-requests.index'))
        ->assertForbidden();

    $this->actingAs($student)
        ->withSession(reservationSession($student))
        ->patch(route('admin.reservation-requests.approve', $request))
        ->assertForbidden();
});

it('approves atomically, creates an approved schedule, and notifies the student', function () {
    Notification::fake();
    $student = reservationUser('student', 'approved');
    $admin = reservationUser('admin', 'approver');
    $room = reservationRoom('approval');
    $request = ReservationRequest::factory()->create([
        'student_id' => $student->id,
        'room_id' => $room->id,
        'purpose' => 'Approved student activity',
        'attendees' => 25,
    ]);

    $this->actingAs($admin)
        ->withSession(reservationSession($admin))
        ->patch(route('admin.reservation-requests.approve', $request))
        ->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe('approved')
        ->and($request->reviewed_by)->toBe($admin->id)
        ->and($request->approved_at)->not->toBeNull()
        ->and($request->schedule_id)->not->toBeNull();
    $this->assertDatabaseHas('schedules', [
        'id' => $request->schedule_id,
        'room_id' => $room->id,
        'requester_id' => $student->id,
        'event_title' => 'Approved student activity',
        'status' => 'approved',
    ]);
    $this->assertDatabaseHas('schedule_notifications', [
        'user_id' => $student->id,
        'reservation_request_id' => $request->id,
        'type' => 'reservation_approved',
    ]);
    Notification::assertSentTo(
        $student,
        ReservationRequestMailNotification::class,
        fn ($notification) => $notification->event === 'approved',
    );
});

it('requires a rejection message and records the rejection notification', function () {
    Notification::fake();
    $student = reservationUser('student', 'rejected');
    $admin = reservationUser('admin', 'rejector');
    $request = ReservationRequest::factory()->create([
        'student_id' => $student->id,
        'room_id' => reservationRoom('rejection')->id,
    ]);

    $this->actingAs($admin)
        ->withSession(reservationSession($admin))
        ->patch(route('admin.reservation-requests.reject', $request), ['admin_response' => ''])
        ->assertSessionHasErrors('admin_response');

    expect($request->fresh()->status)->toBe('pending');

    $this->actingAs($admin)
        ->withSession(reservationSession($admin))
        ->patch(route('admin.reservation-requests.reject', $request), [
            'admin_response' => 'The room is reserved for an academic event. Please select another date.',
        ])
        ->assertRedirect();

    $request->refresh();
    expect($request->status)->toBe('rejected')
        ->and($request->reviewed_by)->toBe($admin->id)
        ->and($request->rejected_at)->not->toBeNull()
        ->and($request->admin_response)->toContain('academic event');
    $this->assertDatabaseHas('schedule_notifications', [
        'user_id' => $student->id,
        'reservation_request_id' => $request->id,
        'type' => 'reservation_rejected',
    ]);
    Notification::assertSentTo(
        $student,
        ReservationRequestMailNotification::class,
        fn ($notification) => $notification->event === 'rejected',
    );
});

it('blocks approval when an approved schedule appeared after submission', function () {
    $student = reservationUser('student', 'late-conflict');
    $admin = reservationUser('admin', 'conflict-admin');
    $room = reservationRoom('late-conflict');
    $request = ReservationRequest::factory()->create([
        'student_id' => $student->id,
        'room_id' => $room->id,
        'reservation_date' => '2026-09-10',
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);
    Schedule::create([
        'room_id' => $room->id,
        'event_title' => 'Imported academic schedule',
        'event_type' => 'class',
        'date' => '2026-09-10',
        'start_time' => '09:30',
        'end_time' => '10:30',
        'day_of_week' => 'Thursday',
        'status' => 'approved',
    ]);

    $this->actingAs($admin)
        ->withSession(reservationSession($admin))
        ->patch(route('admin.reservation-requests.approve', $request))
        ->assertSessionHasErrors('room_id');

    $request->refresh();
    expect($request->status)->toBe('pending')
        ->and($request->schedule_id)->toBeNull()
        ->and(Schedule::count())->toBe(1);
});

it('shows role-scoped dashboard totals, status details, and pending admin count', function () {
    $student = reservationUser('student', 'dashboard');
    $other = reservationUser('student', 'dashboard-other');
    $admin = reservationUser('admin', 'dashboard-admin');
    $room = reservationRoom('dashboard');
    ReservationRequest::factory()->create([
        'student_id' => $student->id,
        'room_id' => $room->id,
        'status' => 'pending',
    ]);
    ReservationRequest::factory()->create([
        'student_id' => $other->id,
        'room_id' => $room->id,
        'status' => 'rejected',
        'admin_response' => 'Choose another time.',
        'rejected_at' => now(),
    ]);

    $this->actingAs($student)
        ->withSession(reservationSession($student))
        ->get(route('main.dashboard'))
        ->assertRedirect(route('student.reservations.index'));

    $this->actingAs($student)
        ->withSession(reservationSession($student))
        ->get(route('student.reservations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MyReservations')
            ->where('totals.all', 1)
            ->where('totals.pending', 1)
            ->has('requests.data', 1)
            ->where('requests.data.0.student.id', $student->id));

    $this->actingAs($admin)
        ->withSession(reservationSession($admin))
        ->get(route('admin.reservation-requests.index', ['status' => 'all']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('ReservationRequests')
            ->where('totals.pending', 1)
            ->where('totals.rejected', 1)
            ->has('requests.data', 2));

    $this->actingAs($admin)
        ->withSession(reservationSession($admin))
        ->get(route('main.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MainDashboard')
            ->where('pendingReservationRequests', 1));
});
