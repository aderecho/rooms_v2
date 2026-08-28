<?php

namespace App\Notifications;

use App\Models\ReservationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReservationRequestMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ReservationRequest $reservationRequest,
        public readonly string $event,
    ) {
        $this->onQueue('mail');
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $request = $this->reservationRequest->loadMissing(['student', 'room.building']);
        $studentName = trim("{$request->student?->first_name} {$request->student?->last_name}")
            ?: ($request->student?->username ?? 'Student');
        $roomName = $request->room?->room_name ?? $request->room?->room_code ?? 'Room';
        $date = $request->reservation_date?->format('F j, Y') ?? 'N/A';
        $time = $request->start_time?->format('g:i A').'–'.$request->end_time?->format('g:i A');
        $isAdmin = ($notifiable->user_type ?? null) === 'admin';
        $actionUrl = $isAdmin
            ? route('admin.reservation-requests.show', $request)
            : route('student.reservations.show', $request);

        $mail = (new MailMessage)
            ->greeting($isAdmin ? 'New student reservation request' : "Hello {$studentName},")
            ->line("Student: {$studentName}")
            ->line("Room: {$roomName}")
            ->line("Schedule: {$date}, {$time}")
            ->line("Purpose: {$request->purpose}")
            ->line("Attendees: {$request->attendees}");

        if ($this->event === 'submitted') {
            return $mail
                ->subject("New reservation request: {$roomName}")
                ->line('The request is pending administrator review.')
                ->action('Review request', $actionUrl);
        }

        if ($this->event === 'approved') {
            return $mail
                ->subject("Reservation approved: {$roomName}")
                ->line('Your reservation request has been approved and added to the room calendar.')
                ->when($request->admin_response, fn (MailMessage $message) => $message->line("Administrator response: {$request->admin_response}"))
                ->action('View reservation', $actionUrl);
        }

        return $mail
            ->subject("Reservation rejected: {$roomName}")
            ->line('Your reservation request was not approved.')
            ->line("Administrator response: {$request->admin_response}")
            ->action('View reservation', $actionUrl);
    }
}
