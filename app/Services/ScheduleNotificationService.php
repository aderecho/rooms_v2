<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleNotification;
use App\Models\UserAccount;
use Illuminate\Http\Request;

class ScheduleNotificationService
{
    public function notifyAdminsOfNewAppointment(Schedule $schedule, ?UserAccount $createdBy = null): void
    {
        $schedule->loadMissing(['room']);

        $admins = UserAccount::query()
            ->where('user_type', 'admin')
            ->where('account_status', 'active')
            ->get();

        $roomName = $schedule->room?->room_name ?? $schedule->room?->room_code ?? 'Unknown room';
        $title = $schedule->event_title ?: 'New appointment';
        $message = sprintf(
            'New appointment "%s" in %s on %s.',
            $title,
            $roomName,
            $schedule->date?->format('M j, Y') ?? 'scheduled date'
        );

        foreach ($admins as $admin) {
            if ($createdBy && $admin->id === $createdBy->id) {
                continue;
            }

            $this->createNotification($admin->id, $schedule->id, 'appointment_created', $title, $message);
        }
    }

    public function notifyBookingConfirmation(Schedule $schedule, UserAccount $user): void
    {
        $schedule->loadMissing(['room']);
        $roomName = $schedule->room?->room_name ?? $schedule->room?->room_code ?? 'Unknown room';
        $title = $schedule->event_title ?: 'Untitled appointment';
        $dateLabel = $schedule->date?->format('M j, Y') ?? 'the selected date';

        $message = sprintf(
            'You have submitted an appointment for "%s" in %s on %s. It is now pending admin review.',
            $title,
            $roomName,
            $dateLabel
        );

        $this->createNotification(
            $user->id,
            $schedule->id,
            'booking_confirmation',
            'You have submitted an appointment',
            $message
        );
    }

    public function notifyStatusChange(Schedule $schedule, string $status, ?UserAccount $recipient): void
    {
        if (!$recipient) {
            return;
        }

        $this->createStatusNotification($recipient->id, $schedule, $status);
    }

    public function notifyStatusChangeToAllRoles(Schedule $schedule, string $status, ?UserAccount $changedBy = null): void
    {
        $schedule->loadMissing(['room', 'requester', 'faculty']);

        $recipientIds = collect();

        if ($schedule->requester_id) {
            $recipientIds->push($schedule->requester_id);
        }

        if ($schedule->faculty_id) {
            $recipientIds->push($schedule->faculty_id);
        }

        $adminIds = UserAccount::query()
            ->where('user_type', 'admin')
            ->where('account_status', 'active')
            ->pluck('id');

        $recipientIds = $recipientIds->merge($adminIds);

        $previouslyNotifiedIds = ScheduleNotification::query()
            ->where('schedule_id', $schedule->id)
            ->distinct()
            ->pluck('user_id');

        $recipientIds = $recipientIds
            ->merge($previouslyNotifiedIds)
            ->unique()
            ->filter()
            ->values();

        foreach ($recipientIds as $userId) {
            $this->createStatusNotification((int) $userId, $schedule, $status, $changedBy);
        }
    }

    private function createStatusNotification(
        int $userId,
        Schedule $schedule,
        string $status,
        ?UserAccount $changedBy = null
    ): void {
        $statusLabel = $this->formatStatusLabel($status);
        $appointmentTitle = $schedule->event_title ?: 'Untitled appointment';
        $roomName = $schedule->room?->room_name ?? $schedule->room?->room_code ?? 'Unknown room';
        $changedByName = trim(($changedBy?->first_name ?? '') . ' ' . ($changedBy?->last_name ?? ''))
            ?: ($changedBy?->username ?? 'An admin');

        if ($changedBy && $userId === $changedBy->id) {
            $title = 'Status updated';
            $message = sprintf(
                'You have updated the status of "%s" to %s.',
                $appointmentTitle,
                $statusLabel
            );
        } elseif ($schedule->requester_id === $userId) {
            $title = 'Appointment status updated';
            $message = sprintf(
                'The status of your appointment "%s" in %s has been updated to %s.',
                $appointmentTitle,
                $roomName,
                $statusLabel
            );
        } else {
            $title = 'Status updated';
            $message = sprintf(
                '%s has updated the status of "%s" to %s.',
                $changedByName,
                $appointmentTitle,
                $statusLabel
            );
        }

        $this->createNotification($userId, $schedule->id, 'status_updated', $title, $message);
    }

    private function formatStatusLabel(string $status): string
    {
        return match (strtolower($status)) {
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'approved' => 'Approved',
            'completed' => 'Completed',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public function resolveCurrentUser(Request $request): ?UserAccount
    {
        $authUser = $request->user();
        if ($authUser instanceof UserAccount) {
            return $authUser;
        }

        if ($authUser && method_exists($authUser, 'getAuthIdentifier')) {
            $byId = UserAccount::find($authUser->getAuthIdentifier());
            if ($byId) {
                return $byId;
            }
        }

        $sessionUser = $request->session()->get('user');
        if (!is_array($sessionUser)) {
            return null;
        }

        if (!empty($sessionUser['id'])) {
            return UserAccount::find($sessionUser['id']);
        }

        if (!empty($sessionUser['username'])) {
            return UserAccount::where('username', $sessionUser['username'])->first();
        }

        if (!empty($sessionUser['email'])) {
            return UserAccount::where('email', $sessionUser['email'])->first();
        }

        return null;
    }

    private function createNotification(
        int $userId,
        int $scheduleId,
        string $type,
        string $title,
        ?string $message
    ): void {
        ScheduleNotification::create([
            'user_id' => $userId,
            'schedule_id' => $scheduleId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }
}
