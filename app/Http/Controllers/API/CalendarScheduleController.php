<?php

namespace App\Http\Controllers\API;

use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class CalendarScheduleController
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);
        $start = CarbonImmutable::createFromFormat('!Y-m', $validated['month']);
        $schedules = Schedule::query()
            ->with('room.building')
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<', $start->addMonth()->toDateString())
            ->when($validated['room_id'] ?? null, fn ($query, $roomId) => $query->where('room_id', $roomId))
            ->orderBy('date')->orderBy('start_time')->orderBy('id')
            ->get()
            ->map(fn (Schedule $schedule) => [
                'id' => $schedule->id,
                'room_id' => $schedule->room_id,
                'room_name' => $schedule->room?->room_name,
                'room_code' => $schedule->room?->room_code,
                'building_name' => $schedule->room?->building?->building_name,
                'event_title' => $schedule->event_title,
                'event_type' => $schedule->event_type,
                'date' => $schedule->date->format('Y-m-d'),
                'start_time' => $schedule->start_time?->format('H:i:s'),
                'end_time' => $schedule->end_time?->format('H:i:s'),
                'status' => $schedule->status,
                'is_recurring' => $schedule->is_recurring,
                'equipment_needed' => $schedule->equipment_needed ?? [],
            ]);

        return response()->json([
            'data' => $schedules,
            'meta' => [
                'month' => $validated['month'],
                'count' => $schedules->count(),
            ],
        ]);
    }
}
