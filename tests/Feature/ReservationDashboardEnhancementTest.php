<?php

use App\Http\Controllers\LoginController;
use App\Models\Building;
use App\Models\ReservationRequest;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\UserAccount;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = UserAccount::factory()->create(['user_type' => 'admin', 'account_status' => 'active']);
    $this->student = UserAccount::factory()->create(['user_type' => 'student', 'account_status' => 'active', 'username' => '2026-00421', 'first_name' => 'Maria', 'last_name' => 'Santos']);
    $this->room = Room::create(['room_name' => 'Conference Hall', 'room_code' => 'CONF-001', 'capacity' => 40, 'status' => 'available']);
    $this->actingAs($this->admin)->withSession(['user' => LoginController::sessionPayload($this->admin)]);
});

function dashboardRequest($test, array $attributes = []): ReservationRequest
{
    return ReservationRequest::factory()->create([
        'student_id' => $test->student->id, 'room_id' => $test->room->id,
        'reservation_date' => now()->addDays(10)->format('Y-m-d'), ...$attributes,
    ]);
}

it('paginates ten by default and prioritizes pending then latest requests', function () {
    for ($i = 0; $i < 12; $i++) {
        dashboardRequest($this, ['status' => 'approved', 'created_at' => now()->addMinutes($i)]);
    }
    $pending = dashboardRequest($this, ['created_at' => now()->subDay()]);
    $this->get('/ReservationRequests?status=all')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('requests.data', 10)->where('requests.meta.total', 13)->where('requests.meta.per_page', 10)
        ->where('requests.meta.from', 1)->where('requests.meta.to', 10)->where('requests.data.0.id', $pending->id)
        ->where('totals.pending', 1)->where('totals.approved', 12)->where('totals.all', 13));
    $this->get('/ReservationRequests?status=all&page=2')->assertInertia(fn (Assert $page) => $page
        ->has('requests.data', 3)->where('requests.meta.from', 11)->where('requests.meta.to', 13));
});

it('accepts every supported page size and rejects arbitrary page sizes', function ($size) {
    $this->get('/ReservationRequests?per_page='.$size)->assertInertia(fn (Assert $page) => $page->where('requests.meta.per_page', $size));
    $this->getJson('/ReservationRequests?per_page=1000')->assertUnprocessable()->assertJsonValidationErrors('per_page');
})->with([10, 25, 50, 100]);

it('combines search date submission date and status and preserves pagination filters', function () {
    $date = now()->addDays(10)->format('Y-m-d');
    for ($i = 0; $i < 12; $i++) {
        dashboardRequest($this, ['purpose' => 'Research workshop']);
    }
    dashboardRequest($this, ['purpose' => 'Research workshop', 'status' => 'rejected']);
    dashboardRequest($this, ['purpose' => 'Research workshop', 'created_at' => now()->subDays(2)]);
    $response = $this->get('/ReservationRequests?'.http_build_query(['status' => 'pending', 'search' => 'Research', 'date' => $date, 'submitted_date' => today()->format('Y-m-d'), 'per_page' => 10]));
    $response->assertInertia(fn (Assert $page) => $page->where('requests.meta.total', 12)->has('requests.data', 10)
        ->where('filters.submitted_date', today()->format('Y-m-d'))
        ->where('requests.links.next', fn ($url) => str_contains($url, 'search=Research') && str_contains($url, 'submitted_date=') && str_contains($url, 'status=pending') && str_contains($url, 'per_page=10')));
});

it('searches full names account identifiers email room codes and buildings', function ($term) {
    $building = Building::factory()->create(['building_name' => 'Science Center']);
    $this->room->update(['building_id' => $building->id]);
    $this->student->update(['email' => 'maria.santos@example.test']);
    dashboardRequest($this);
    $this->get('/ReservationRequests?search='.urlencode($term))->assertInertia(fn (Assert $page) => $page->has('requests.data', 1));
})->with(['Maria Santos', '2026-00421', 'maria.santos@example.test', 'CONF-001', 'Science Center']);

it('rejects inactive admins and other roles for lists details and decisions', function ($role, $active) {
    $user = UserAccount::factory()->create(['user_type' => $role, 'account_status' => $active]);
    $request = dashboardRequest($this);
    $this->actingAs($user)->withSession(['user' => LoginController::sessionPayload($user)]);
    $this->get('/ReservationRequests')->assertForbidden();
    $this->get('/ReservationRequests/'.$request->id)->assertForbidden();
    $this->patch('/ReservationRequests/'.$request->id.'/approve')->assertForbidden();
    $this->patch('/ReservationRequests/'.$request->id.'/reject', ['admin_response' => 'Unavailable room'])->assertForbidden();
})->with([['faculty', 'active'], ['student', 'active'], ['admin', 'inactive']]);

it('records immutable decision history and does not duplicate schedules emails or decisions', function () {
    $request = dashboardRequest($this);
    $this->patch('/ReservationRequests/'.$request->id.'/approve')->assertSessionHasNoErrors();
    $this->patch('/ReservationRequests/'.$request->id.'/approve')->assertForbidden();
    $this->patch('/ReservationRequests/'.$request->id.'/reject', ['admin_response' => 'Changed my mind'])->assertForbidden();
    expect(Schedule::count())->toBe(1);
    expect($request->history()->count())->toBe(1);
    expect($request->mailDeliveries()->count())->toBe(1);
    $this->get('/ReservationRequests/'.$request->id)->assertInertia(fn (Assert $page) => $page
        ->where('reservationRequest.data.history.0.actor', trim($this->admin->first_name.' '.$this->admin->last_name))
        ->where('reservationRequest.data.history.0.to_status', 'approved')
        ->where('reservationRequest.data.student.account_identifier', '2026-00421'));
});

it('rechecks availability capacity operating hours and past dates before approval', function ($failure) {
    $attributes = match ($failure) {
        'past' => ['reservation_date' => today()->subDay()->format('Y-m-d')],
        'hours' => ['start_time' => '06:00', 'end_time' => '07:00'],
        'capacity' => ['attendees' => 41],
        default => [],
    };
    $request = dashboardRequest($this, $attributes);
    $this->patch('/ReservationRequests/'.$request->id.'/approve')->assertSessionHasErrors();
    expect($request->fresh()->status)->toBe('pending');
    expect($request->history()->count())->toBe(0);
    expect($request->mailDeliveries()->count())->toBe(0);
})->with(['past', 'hours', 'capacity']);

it('blocks another approval for an overlapping room time and allows adjacent times', function () {
    $first = dashboardRequest($this);
    $overlap = dashboardRequest($this, ['start_time' => '09:30', 'end_time' => '10:30']);
    $adjacent = dashboardRequest($this, ['start_time' => '10:00', 'end_time' => '11:00']);
    $this->patch('/ReservationRequests/'.$first->id.'/approve')->assertSessionHasNoErrors();
    expect($overlap->fresh()->status)->toBe('rejected');
    expect($overlap->history()->first()->to_status)->toBe('rejected');
    expect($overlap->mailDeliveries()->count())->toBe(1);
    $this->patch('/ReservationRequests/'.$overlap->id.'/approve')->assertForbidden();
    $this->patch('/ReservationRequests/'.$adjacent->id.'/approve')->assertSessionHasNoErrors();
    expect(Schedule::count())->toBe(2);
});

it('requires a rejection reason and records the actual approver rather than client identity', function () {
    $request = dashboardRequest($this);
    $this->patch('/ReservationRequests/'.$request->id.'/reject', ['admin_response' => '   '])->assertSessionHasErrors('admin_response');
    $this->patch('/ReservationRequests/'.$request->id.'/reject', ['admin_response' => 'Please choose another date.', 'reviewed_by' => $this->student->id, 'status' => 'approved'])->assertSessionHasNoErrors();
    expect($request->fresh()->reviewed_by)->toBe($this->admin->id);
    expect($request->history()->first()->remarks)->toBe('Please choose another date.');
    $this->patch('/ReservationRequests/'.$request->id.'/reject', ['admin_response' => 'Another message'])->assertForbidden();
});

it('rechecks the locked status when a competing decision uses a stale pending model', function () {
    $request = dashboardRequest($this);
    app(\App\Services\ReservationRequestService::class)->approve($request, $this->admin);
    expect(fn () => app(\App\Services\ReservationRequestService::class)->reject($request, $this->admin, 'Competing decision'))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
    expect($request->fresh()->status)->toBe('approved');
    expect($request->history()->count())->toBe(1);
    expect($request->mailDeliveries()->count())->toBe(1);
});

it('requires a valid saved student email and ignores a client supplied substitute', function () {
    $this->student->update(['email' => 'invalid-email']);
    $this->actingAs($this->student)->withSession(['user' => LoginController::sessionPayload($this->student)]);
    $this->postJson('/MyReservations', [
        'room_id' => $this->room->id, 'reservation_date' => today()->addDays(10)->format('Y-m-d'),
        'start_time' => '09:00', 'end_time' => '10:00', 'attendees' => 20, 'purpose' => 'Research workshop',
        'email' => 'substitute@example.test',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
    expect(ReservationRequest::count())->toBe(0);
});
