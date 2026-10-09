<?php

namespace App\Services;

use App\Models\ReservationRequest;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\UserAccount;
use Illuminate\Support\Facades\DB;

class ScheduleApprovalService
{
    public function approve(Schedule $schedule, ?UserAccount $admin): Schedule
    {
        return DB::transaction(function () use ($schedule, $admin) {
            Room::whereKey($schedule->room_id)->lockForUpdate()->firstOrFail();
            $schedule = Schedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            if (! in_array($schedule->status, ['pending', 'approved', 'in_progress'], true)) {
                return $schedule;
            }
            $overlaps = fn () => Schedule::where('room_id', $schedule->room_id)
                ->whereDate('date', $schedule->date->format('Y-m-d'))
                ->whereTime('start_time', '<', $schedule->end_time->format('H:i:s'))
                ->whereTime('end_time', '>', $schedule->start_time->format('H:i:s'))
                ->whereKeyNot($schedule->id);
            $notifications = app(ScheduleNotificationService::class);
            if ($overlaps()->whereIn('status', ['approved', 'in_progress'])->exists()) {
                if ($schedule->status === 'pending') {
                    $schedule->update(['status' => 'rejected']);
                    $notifications->notifyStatusChangeToAllRoles($schedule, 'rejected', $admin);
                }
                return $schedule;
            }
            if ($schedule->status === 'pending') {
                $schedule->update(['status' => 'approved']);
                $notifications->notifyStatusChangeToAllRoles($schedule, 'approved', $admin);
            }
            $overlaps()->where('status', 'pending')->lockForUpdate()->chunkById(100, function ($schedules) use ($notifications, $admin) {
                foreach ($schedules as $other) {
                    $other->update(['status' => 'rejected']);
                    $notifications->notifyStatusChangeToAllRoles($other, 'rejected', $admin);
                }
            });
            if ($admin) {
                $requests = ReservationRequest::where('room_id', $schedule->room_id)
                    ->whereDate('reservation_date', $schedule->date->format('Y-m-d'))
                    ->whereTime('start_time', '<', $schedule->end_time->format('H:i:s'))
                    ->whereTime('end_time', '>', $schedule->start_time->format('H:i:s'))
                    ->where('status', 'pending')->lockForUpdate();
                $requests->chunkById(100, function ($requests) use ($admin) {
                    foreach ($requests as $request) {
                        app(ReservationRequestService::class)->reject($request, $admin,
                            'Automatically rejected because another booking was approved for this room during the requested time.');
                    }
                });
            }
            return $schedule;
        }, 3);
    }
}
