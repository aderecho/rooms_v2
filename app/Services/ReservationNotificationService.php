<?php

namespace App\Services;

use App\Models\ReservationRequest;
use App\Models\ScheduleNotification;
use App\Models\UserAccount;
use App\Notifications\ReservationRequestMailNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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

        return $admins;
    }

    public function notifyStudentInSystem(ReservationRequest $request, string $event): void
    {
        $request->loadMissing(['student', 'room']);
        if (! $request->student) {
            return;
        }

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
        foreach ($admins as $admin) {
            $this->queueMailSafely($admin, $request, 'submitted');
        }
    }

    public function queueStudentEmail(ReservationRequest $request, string $event): void
    {
        $request->loadMissing('student');
        if ($request->student) {
            $this->queueMailSafely($request->student, $request, $event);
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

    private function queueMailSafely(
        UserAccount $recipient,
        ReservationRequest $request,
        string $event,
    ): void {
        try {
            $recipient->notify(new ReservationRequestMailNotification($request, $event));
        } catch (Throwable $exception) {
            Log::error('Reservation email could not be queued.', [
                'reservation_request_id' => $request->id,
                'recipient_id' => $recipient->id,
                'event' => $event,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
