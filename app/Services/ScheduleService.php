<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\Room;
use App\Models\UserAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    public function getAllSchedules($search = null, $filters = [])
    {
        $query = Schedule::with([
                'room:id,room_name,room_code',
                'faculty:id,first_name,last_name',
                'requester:id,first_name,last_name',
                'term:id,term_name'
            ])
            ->orderBy('date', 'desc')
            ->orderBy('start_time');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('event_title', 'like', "%{$search}%")
                  ->orWhere('course_code', 'like', "%{$search}%")
                  ->orWhere('course_name', 'like', "%{$search}%")
                  ->orWhere('faculty_name', 'like', "%{$search}%")
                  ->orWhere('cfic_id', 'like', "%{$search}%")
                  ->orWhereHas('room', function ($q) use ($search) {
                      $q->where('room_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('faculty', function ($q) use ($search) {
                      $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['room_id'])) {
            $query->where('room_id', $filters['room_id']);
        }

        if (!empty($filters['faculty_id'])) {
            $query->where('faculty_id', $filters['faculty_id']);
        }

        if (!empty($filters['date'])) {
            $query->where('date', $filters['date']);
        }

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('date', [$filters['date_from'], $filters['date_to']]);
        }

        if (!empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        return $query->paginate(10);
    }

    public function getScheduleStats($period = 'week')
    {
        $now = Carbon::now();

        switch ($period) {
            case 'day':
                $startDate = $now->copy()->startOfDay();
                $endDate = $now->copy()->endOfDay();
                break;
            case 'week':
                $startDate = $now->copy()->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                break;
            case 'month':
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                break;
            default:
                $startDate = $now->copy()->subDays(30);
                $endDate = $now->copy();
                break;
        }

        return [
            'total_schedules' => Schedule::whereBetween('date', [$startDate, $endDate])->count(),
            'approved' => Schedule::whereBetween('date', [$startDate, $endDate])
                ->where('status', 'approved')
                ->count(),
            'pending' => Schedule::whereBetween('date', [$startDate, $endDate])
                ->where('status', 'pending')
                ->count(),
            'cancelled' => Schedule::whereBetween('date', [$startDate, $endDate])
                ->where('status', 'cancelled')
                ->count(),
            'completed' => Schedule::whereBetween('date', [$startDate, $endDate])
                ->where('status', 'completed')
                ->count(),
            'by_event_type' => Schedule::whereBetween('date', [$startDate, $endDate])
                ->select('event_type', DB::raw('count(*) as count'))
                ->groupBy('event_type')
                ->get()
                ->pluck('count', 'event_type')
                ->toArray(),
        ];
    }

    public function checkRoomAvailability($roomId, $date, $startTime, $endTime, $excludeScheduleId = null)
    {
        $query = Schedule::where('room_id', $roomId)
            ->where('date', $date)
            ->where('status', 'approved')
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where(function ($inner) use ($startTime, $endTime) {
                    // Check for overlapping time ranges
                    $inner->whereBetween('start_time', [$startTime, $endTime])
                          ->orWhereBetween('end_time', [$startTime, $endTime])
                          ->orWhere(function ($inner2) use ($startTime, $endTime) {
                              $inner2->where('start_time', '<=', $startTime)
                                     ->where('end_time', '>=', $endTime);
                          });
                });
            });

        if ($excludeScheduleId) {
            $query->where('id', '!=', $excludeScheduleId);
        }

        return $query->count() === 0;
    }

    public function createSchedule($data)
    {
        // Check room availability before creating schedule
        $isAvailable = $this->checkRoomAvailability(
            $data['room_id'],
            $data['date'],
            $data['start_time'],
            $data['end_time']
        );

        if (!$isAvailable) {
            throw new \Exception('Room is not available at the selected time.');
        }

        return Schedule::create($data);
    }

    public function updateSchedule($scheduleId, $data)
    {
        $schedule = Schedule::findOrFail($scheduleId);

        // Check room availability (excluding current schedule)
        $isAvailable = $this->checkRoomAvailability(
            $data['room_id'] ?? $schedule->room_id,
            $data['date'] ?? $schedule->date,
            $data['start_time'] ?? $schedule->start_time,
            $data['end_time'] ?? $schedule->end_time,
            $scheduleId
        );

        if (!$isAvailable) {
            throw new \Exception('Room is not available at the selected time.');
        }

        $schedule->update($data);
        return $schedule;
    }

    public function deleteSchedule($scheduleId)
    {
        $schedule = Schedule::findOrFail($scheduleId);
        $schedule->delete();
        return true;
    }

    public function getRoomSchedules($roomId, $date = null)
    {
        $date = $date ?: now()->format('Y-m-d');

        return Schedule::where('room_id', $roomId)
            ->where('date', $date)
            ->where('status', 'approved')
            ->orderBy('start_time')
            ->get();
    }

    public function approveSchedule($scheduleId)
    {
        $schedule = Schedule::findOrFail($scheduleId);

        // Check room availability before approving
        $isAvailable = $this->checkRoomAvailability(
            $schedule->room_id,
            $schedule->date,
            $schedule->start_time,
            $schedule->end_time,
            $scheduleId
        );

        if (!$isAvailable) {
            throw new \Exception('Cannot approve schedule. Room is not available at the selected time.');
        }

        $schedule->update(['status' => 'approved']);
        return $schedule;
    }
}
