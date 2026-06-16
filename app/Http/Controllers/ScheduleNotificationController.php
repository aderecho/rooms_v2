<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\ScheduleNotification;
use App\Services\ScheduleNotificationService;
use Illuminate\Http\Request;

class ScheduleNotificationController extends Controller
{
    public function __construct(
        private readonly ScheduleNotificationService $notificationService
    ) {}

    public function index(Request $request)
    {
        $user = $this->notificationService->resolveCurrentUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $notifications = ScheduleNotification::query()
            ->with(['schedule.room'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (ScheduleNotification $notification) => $this->formatNotification($notification));

        $unreadCount = ScheduleNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'is_admin' => $user->user_type === 'admin',
        ]);
    }

    public function markRead(Request $request, ScheduleNotification $notification)
    {
        $user = $this->notificationService->resolveCurrentUser($request);
        if (!$user || $notification->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request)
    {
        $user = $this->notificationService->resolveCurrentUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        ScheduleNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function markAllUnread(Request $request)
    {
        $user = $this->notificationService->resolveCurrentUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        ScheduleNotification::query()
            ->where('user_id', $user->id)
            ->whereNotNull('read_at')
            ->update(['read_at' => null]);

        return response()->json(['success' => true]);
    }

    public function clearAll(Request $request)
    {
        $user = $this->notificationService->resolveCurrentUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        ScheduleNotification::query()
            ->where('user_id', $user->id)
            ->delete();

        return response()->json(['success' => true]);
    }

    private function formatNotification(ScheduleNotification $notification): array
    {
        $schedule = $notification->schedule;
        $roomName = $schedule?->room?->room_name ?? $schedule?->room?->room_code ?? 'N/A';

        return [
            'id' => $notification->id,
            'schedule_id' => $notification->schedule_id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
            'schedule' => $schedule ? [
                'id' => $schedule->id,
                'title' => $schedule->event_title,
                'status' => $schedule->status,
                'room' => $roomName,
                'date' => optional($schedule->date)->format('Y-m-d'),
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
            ] : null,
        ];
    }
}
