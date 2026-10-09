<?php

namespace App\Notifications;

use App\Models\ReservationMailDelivery;
use App\Models\ReservationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReservationRequestMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout = 60;

    public function __construct(
        public readonly ReservationRequest $reservationRequest,
        public readonly string $event,
        public readonly ?int $deliveryId = null,
    ) {
        $this->tries = config('reservations.mail_tries', 3);
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function backoff(): array
    {
        return config('reservations.mail_backoff', [60, 300, 900]);
    }

    public function middleware(object $notifiable, string $channel): array
    {
        return $this->deliveryId ? [(new WithoutOverlapping('reservation-mail-'.$this->deliveryId))->releaseAfter(15)->expireAfter(75)] : [];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! $this->deliveryId || ReservationMailDelivery::whereKey($this->deliveryId)->where('status', '!=', 'sent')->exists();
    }

    public function failed(Throwable $exception): void
    {
        if ($this->deliveryId) {
            ReservationMailDelivery::whereKey($this->deliveryId)->where('status', '!=', 'sent')
                ->update(['status' => 'failed', 'last_error' => $exception::class]);
        }
        Log::error('Reservation email delivery exhausted retries.', ['delivery_id' => $this->deliveryId, 'reservation_request_id' => $this->reservationRequest->id, 'exception' => $exception::class]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->reservationRequest->loadMissing(['student', 'room.building', 'reviewer']);
        if ($this->deliveryId) {
            ReservationMailDelivery::whereKey($this->deliveryId)->increment('attempts');
            Log::info('Reservation email delivery attempt.', ['delivery_id' => $this->deliveryId, 'event' => $this->event]);
        }
        $studentName = trim("{$request->student?->first_name} {$request->student?->last_name}") ?: ($request->student?->username ?? 'Student');
        [$subject, $heading, $intro, $actionLabel, $status] = match ($this->event) {
            'submitted' => ["[Room Reservation] New Reservation Request #{$request->id}", 'New Room Reservation Request', 'A student has submitted a room reservation for your review.', 'Review Reservation Request', 'Pending'],
            'approved' => ["[Room Reservation] Your Request #{$request->id} Has Been Approved", 'Reservation Request Approved', 'Your reservation has been approved and added to the room calendar.', 'View Reservation Details', 'Approved'],
            default => ["[Room Reservation] Update on Your Request #{$request->id}", 'Reservation Request Update', 'Your request was not approved. Please review the decision below.', 'View Request Details', 'Rejected'],
        };
        $path = $this->event === 'submitted'
            ? route('admin.reservation-requests.show', $request, false)
            : route('student.reservations.show', $request, false);
        $portal = rtrim(config('reservations.portal_url'), '/');
        if (app()->environment('production') && parse_url($portal, PHP_URL_SCHEME) !== 'https') {
            throw new \RuntimeException('Reservation portal must use HTTPS in production.');
        }
        $actionUrl = $portal.$path;
        $logoUrl = config('reservations.logo_url') ?: $portal.'/image/uplogo.png';
        if (app()->environment('production') && parse_url($logoUrl, PHP_URL_SCHEME) !== 'https') {
            $logoUrl = null;
        }
        $details = [
            'Request reference' => '#'.$request->id,
            'Student' => $studentName,
            'Account identifier' => $request->student?->username ?? 'Not provided',
            'Room' => trim(($request->room?->room_code ?? '').' — '.($request->room?->room_name ?? 'Room')),
            'Building' => $request->room?->building?->building_name ?? 'Not specified',
            'Location' => $request->room?->location ?? 'Not specified',
            'Reservation date' => $request->reservation_date?->format('F j, Y'),
            'Schedule' => $request->start_time?->format('g:i A').' – '.$request->end_time?->format('g:i A'),
            'Expected attendees' => $request->attendees,
            'Purpose' => $request->purpose,
            'Remarks' => $request->remarks ?: 'No remarks provided',
            'Submitted' => $request->created_at?->format('F j, Y, g:i A'),
        ];
        if ($this->event === 'submitted') {
            $details['Student email'] = $request->student?->email ?? 'Not provided';
        } else {
            $details['Decision date'] = ($request->approved_at ?? $request->rejected_at)?->format('F j, Y, g:i A') ?? 'Not recorded';
            $details['Reviewed by'] = $request->reviewer ? (trim($request->reviewer->first_name.' '.$request->reviewer->last_name) ?: $request->reviewer->username) : 'Reservation office';
        }
        $data = compact('heading', 'intro', 'studentName', 'status', 'details', 'actionLabel', 'actionUrl', 'logoUrl') + [
            'organization' => config('reservations.organization'),
            'contactEmail' => config('reservations.contact_email'),
            'reason' => $this->event === 'rejected' ? $request->admin_response : null,
            'instructions' => $this->event === 'approved' ? config('reservations.instructions') : null,
        ];

        return (new MailMessage)->subject($subject)->line($request->purpose)
            ->line($request->admin_response ?? $intro)->action($actionLabel, $actionUrl)
            ->view(['html' => 'emails.reservations.message', 'text' => 'emails.reservations.text'], $data);
    }
}
