<?php

namespace App\Services;

use App\Models\ReservationMailDelivery;
use App\Models\ReservationRequest;
use App\Models\ScheduleNotification;
use App\Models\UserAccount;
use App\Notifications\ReservationRequestMailNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ReservationNotificationService
{
    public function activeAdmins(): Collection
    {
        return UserAccount::query()
            ->where('user_type', 'admin')
            ->where('account_status', 'active')
            ->get();
    }

    public function notifyAdminsInSystem(ReservationRequest $request): Collection
    {
        $request->loadMissing(['student', 'room']);
        $admins = $this->activeAdmins();
        $studentName = trim("{$request->student?->first_name} {$request->student?->last_name}")
            ?: ($request->student?->username ?? 'A student');
        $roomName = $request->room?->room_name ?? $request->room?->room_code ?? 'Unknown room';
        $message = sprintf(
            '%s requested %s on %s from %s to %s for %s.',
            $studentName,
            $roomName,
            $request->reservation_date?->format('M j, Y'),
            $request->start_time?->format('g:i A'),
            $request->end_time?->format('g:i A'),
            $request->purpose,
        );

        foreach ($admins as $admin) {
            $this->create(
                $admin,
                $request,
                'reservation_submitted',
                'New reservation request',
                $message,
                route('admin.reservation-requests.show', $request, false),
            );
        }

        foreach ($admins as $admin) {
            $this->recordMail($request, 'submitted', $admin->email, $admin->id);
        }
        foreach (config('reservations.additional_recipients', []) as $email) {
            $this->recordMail($request, 'submitted', $email);
        }

        return $admins;
    }

    public function notifyStudentInSystem(ReservationRequest $request, string $event): void
    {
        $request->loadMissing(['student', 'room']);
        if (! $request->student) {
            return;
        }

        $this->recordMail($request, $event, $request->student->email, $request->student->id);
        $approved = $event === 'approved';
        $roomName = $request->room?->room_name ?? $request->room?->room_code ?? 'your selected room';
        $title = $approved ? 'Reservation approved' : 'Reservation rejected';
        $message = $approved
            ? "Your reservation for {$roomName} has been approved and added to the calendar."
            : "Your reservation for {$roomName} was rejected. {$request->admin_response}";

        $this->create(
            $request->student,
            $request,
            "reservation_{$event}",
            $title,
            $message,
            route('student.reservations.show', $request, false),
        );
    }

    public function queueAdminEmails(Collection $admins, ReservationRequest $request): void
    {
        $this->queueRecordedEmails($request, 'submitted');
    }

    public function queueStudentEmail(ReservationRequest $request, string $event): void
    {
        $this->queueRecordedEmails($request, $event);
    }

    private function recordMail(ReservationRequest $request, string $event, ?string $email, ?int $recipientId = null): void
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Reservation mail recipient has no valid email.', ['reservation_request_id' => $request->id, 'recipient_id' => $recipientId]);

            return;
        }
        ReservationMailDelivery::firstOrCreate([
            'reservation_request_id' => $request->id,
            'event' => $event,
            'recipient_email' => mb_strtolower(trim($email)),
        ], ['recipient_id' => $recipientId]);
    }

    private function queueRecordedEmails(ReservationRequest $request, string $event): void
    {
        // Defers queue publication until the outermost transaction commits.
        DB::afterCommit(function () use ($request, $event) {
            ReservationMailDelivery::where('reservation_request_id', $request->id)
                ->where('event', $event)->where('status', 'pending')
                ->each(fn ($delivery) => $this->dispatchDelivery($delivery));
        });
    }

    public function dispatchDelivery(ReservationMailDelivery $delivery): void
    {
        $claimed = ReservationMailDelivery::whereKey($delivery->id)->where('status', 'pending')
            ->update(['status' => 'queued', 'queued_at' => now(), 'last_error' => null]);
        if (! $claimed) {
            return;
        }
        $delivery->refresh();
        try {
            $notification = new ReservationRequestMailNotification($delivery->reservationRequest, $delivery->event, $delivery->id);
            // Route to the captured address, including configured additional recipients.
            Notification::route('mail', $delivery->recipient_email)->notify($notification);
            ReservationMailDelivery::whereKey($delivery->id)->where('status', '!=', 'sent')
                ->update(['status' => 'queued', 'queued_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            ReservationMailDelivery::whereKey($delivery->id)->where('status', '!=', 'sent')->update(['status' => 'pending', 'last_error' => $exception::class]);
            Log::error('Reservation email could not be queued.', [
                'delivery_id' => $delivery->id, 'reservation_request_id' => $delivery->reservation_request_id,
                'event' => $delivery->event, 'exception' => $exception::class,
            ]);
        }
    }

    private function create(
        UserAccount $recipient,
        ReservationRequest $request,
        string $type,
        string $title,
        string $message,
        string $actionUrl,
    ): void {
        ScheduleNotification::create([
            'user_id' => $recipient->id,
            'schedule_id' => $request->schedule_id,
            'reservation_request_id' => $request->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
        ]);
    }
}
