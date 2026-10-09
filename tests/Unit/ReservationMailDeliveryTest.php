<?php

use App\Models\ReservationMailDelivery;
use App\Models\ReservationRequest;
use App\Models\Room;
use App\Models\UserAccount;
use App\Notifications\ReservationRequestMailNotification;
use App\Services\ReservationNotificationService;
use App\Services\ReservationRequestService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class, DatabaseMigrations::class);

beforeEach(function () {
    config(['mail.default' => 'array', 'queue.default' => 'database', 'reservations.additional_recipients' => [], 'reservations.portal_url' => 'https://rooms.example.test']);
    $this->student = UserAccount::factory()->create(['user_type' => 'student', 'account_status' => 'active']);
    $this->admin = UserAccount::factory()->create(['user_type' => 'admin', 'account_status' => 'active']);
    $this->room = Room::create(['room_name' => 'Mail Test Room', 'room_code' => 'MAIL-TEST', 'capacity' => 40, 'status' => 'available']);
    $this->payload = ['room_id' => $this->room->id, 'reservation_date' => today()->addDays(10)->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '10:00', 'purpose' => 'Research & planning', 'attendees' => 20];
});

it('queues after the outermost commit and does not send on rollback', function () {
    Queue::fake();
    DB::beginTransaction();
    app(ReservationRequestService::class)->submit($this->student, $this->payload);
    Queue::assertNotPushed(SendQueuedNotifications::class);
    expect(ReservationMailDelivery::count())->toBe(1);
    DB::rollBack();
    Queue::assertNotPushed(SendQueuedNotifications::class);
    expect(ReservationMailDelivery::count())->toBe(0);
    DB::beginTransaction();
    app(ReservationRequestService::class)->submit($this->student, $this->payload);
    Queue::assertNotPushed(SendQueuedNotifications::class);
    DB::commit();
    Queue::assertPushedOn('mail', SendQueuedNotifications::class);
    expect(ReservationMailDelivery::first()->status)->toBe('queued');
});

it('captures approved and rejected mail after recording the decision', function ($event) {
    Queue::fake();
    $request = ReservationRequest::create([...$this->payload, 'student_id' => $this->student->id]);
    $service = app(ReservationRequestService::class);
    if ($event === 'approved') {
        $service->approve($request, $this->admin);
    } else {
        $service->reject($request, $this->admin, 'Please choose another date.');
    }
    Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification->event === $event && $job->notification->afterCommit === true);
    expect($request->fresh()->status)->toBe($event);
    expect(ReservationMailDelivery::first()->recipient_email)->toBe(mb_strtolower($this->student->email));
})->with(['approved', 'rejected']);

it('tracks attempts and success and skips duplicate deliveries', function () {
    Queue::fake();
    $request = app(ReservationRequestService::class)->submit($this->student, $this->payload);
    $delivery = ReservationMailDelivery::first();
    $notification = new ReservationRequestMailNotification($request, 'submitted', $delivery->id);
    $recipient = (new \Illuminate\Notifications\AnonymousNotifiable)->route('mail', $delivery->recipient_email);
    Notification::sendNow($recipient, $notification);
    expect($delivery->fresh()->status)->toBe('sent');
    expect($delivery->fresh()->attempts)->toBe(1);
    Notification::sendNow($recipient, $notification);
    expect($delivery->fresh()->attempts)->toBe(1);
    app(ReservationNotificationService::class)->dispatchDelivery($delivery->fresh());
    Queue::assertPushed(SendQueuedNotifications::class, 1);
});

it('keeps committed decisions when the queue is unavailable and recovers pending mail', function () {
    $request = ReservationRequest::create([...$this->payload, 'student_id' => $this->student->id]);
    $originalDispatcher = app(\Illuminate\Contracts\Notifications\Dispatcher::class);
    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Secret SMTP credential must not be stored'));
    app(ReservationRequestService::class)->approve($request, $this->admin);
    expect($request->fresh()->status)->toBe('approved');
    $delivery = ReservationMailDelivery::first();
    expect($delivery->status)->toBe('pending');
    expect($delivery->last_error)->toBe(RuntimeException::class);
    Notification::swap($originalDispatcher);
    Queue::fake();
    $this->artisan('reservations:retry-mail')->assertSuccessful();
    expect($delivery->fresh()->status)->toBe('queued');
    expect($request->history()->count())->toBe(1);
    Queue::assertPushed(SendQueuedNotifications::class, 1);
});

it('records exhausted failures and supports a bounded manual retry', function () {
    Queue::fake();
    $request = app(ReservationRequestService::class)->submit($this->student, $this->payload);
    $delivery = ReservationMailDelivery::first();
    $notification = new ReservationRequestMailNotification($request, 'submitted', $delivery->id);
    $notification->failed(new RuntimeException('Password must never be persisted'));
    expect($delivery->fresh()->status)->toBe('failed');
    expect($delivery->fresh()->last_error)->toBe(RuntimeException::class);
    $this->artisan('reservations:retry-mail', ['--failed' => true])->assertSuccessful();
    expect($delivery->fresh()->status)->toBe('queued');
    Queue::assertPushed(SendQueuedNotifications::class, 2);
    $this->artisan('reservations:retry-mail', ['--stale' => 1])->assertFailed();
});

it('deduplicates additional recipients and excludes invalid addresses', function () {
    Queue::fake();
    config(['reservations.additional_recipients' => [$this->admin->email, 'office@example.test', 'office@example.test', 'invalid']]);
    app(ReservationRequestService::class)->submit($this->student, $this->payload);
    expect(ReservationMailDelivery::count())->toBe(2);
    Queue::assertPushed(SendQueuedNotifications::class, 2);
});

it('renders branded escaped HTML and a meaningful plain text alternative', function ($event) {
    $request = ReservationRequest::create([...$this->payload, 'student_id' => $this->student->id, 'purpose' => '<script>alert(1)</script>', 'admin_response' => 'Please choose another date.']);
    $mail = (new ReservationRequestMailNotification($request, $event))->toMail($this->student);
    $html = view($mail->view['html'], $mail->viewData)->render();
    $text = view($mail->view['text'], $mail->viewData)->render();
    expect($html)->toContain('#005740', 'University of the Philippines Cebu', 'https://rooms.example.test/image/uplogo.png', '&lt;script&gt;');
    expect($html)->not->toContain('<script>');
    expect($text)->toContain('Request reference: #'.$request->id, 'Mail Test Room', '<script>alert(1)</script>');
    expect($mail->actionUrl)->toContain('https://rooms.example.test/'.($event === 'submitted' ? 'ReservationRequests' : 'MyReservations'));
    expect($mail->subject)->toContain('#'.$request->id);
    if ($event === 'rejected') {
        expect($html)->toContain('Please choose another date.');
        expect($text)->toContain('Please choose another date.');
    }
})->with(['submitted', 'approved', 'rejected']);

it('recovers only stale queued deliveries and never republishes sent records', function () {
    Queue::fake();
    app(ReservationRequestService::class)->submit($this->student, $this->payload);
    $delivery = ReservationMailDelivery::first();
    $this->artisan('reservations:retry-mail', ['--stale' => 60])->assertSuccessful();
    Queue::assertPushed(SendQueuedNotifications::class, 1);
    $delivery->update(['queued_at' => now()->subHours(2)]);
    $this->artisan('reservations:retry-mail', ['--stale' => 60])->assertSuccessful();
    Queue::assertPushed(SendQueuedNotifications::class, 2);
    $delivery->update(['status' => 'sent', 'sent_at' => now()]);
    $this->artisan('reservations:retry-mail', ['--stale' => 60, '--failed' => true])->assertSuccessful();
    Queue::assertPushed(SendQueuedNotifications::class, 2);
});
