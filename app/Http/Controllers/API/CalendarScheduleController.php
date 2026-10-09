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
            'month' => ['required_without:start', 'nullable', 'date_format:Y-m'],
            'start' => ['required_without:month', 'nullable', 'date_format:Y-m-d'],
            'end' => ['required_with:start', 'nullable', 'date_format:Y-m-d', 'after_or_equal:start'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
        ]);
        $start = ! empty($validated['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $validated['month'])
            : CarbonImmutable::parse($validated['start']);
        $end = ! empty($validated['month']) ? $start->endOfMonth() : CarbonImmutable::parse($validated['end']);
        abort_if($start->diffInDays($end) > 62, 422, 'Schedule range cannot exceed 62 days.');
        $includeEquipment = ! $request->routeIs('schedules.allocations');
        $roomColumns = 'room:id,room_name,room_code,building_id,college_id'.($includeEquipment ? ',equipments' : '');
        $schedules = Schedule::query()
            ->select('id', 'room_id', 'event_title', 'event_type', 'course_code', 'course_name', 'section', 'cfic_id', 'faculty_id', 'faculty_name', 'day_of_week', 'number_of_participants', 'date', 'start_time', 'end_time', 'status', 'is_recurring', 'equipment_needed')
            ->with([$roomColumns, 'room.building:id,building_name', 'room.college:id,college_name', 'faculty:id,first_name,middle_name,last_name'])
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($validated['room_id'] ?? null, fn ($query, $roomId) => $query->where('room_id', $roomId))
            ->when($validated['building_id'] ?? null, fn ($query, $id) => $query->whereHas('room', fn ($room) => $room->where('building_id', $id)))
            ->orderBy('date')->orderBy('start_time')->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 100));
        $data = $schedules->getCollection()->map(fn (Schedule $schedule) => [
                'id' => $schedule->id,
                'room_id' => $schedule->room_id,
                'room_name' => $schedule->room?->room_name,
                'room_code' => $schedule->room?->room_code,
                ...($includeEquipment ? ['equipments' => $schedule->room?->equipments ?? []] : []),
                'building_name' => $schedule->room?->building?->building_name,
                'event_title' => $schedule->event_title,
                'event_type' => $schedule->event_type,
                'college_name' => $schedule->room?->college?->college_name,
                'course_code' => $schedule->course_code,
                'course_name' => $schedule->course_name,
                'section' => $schedule->section,
                'cfic_id' => $schedule->cfic_id,
                'faculty_name' => $schedule->faculty_name ?: $schedule->faculty?->full_name,
                'day' => $schedule->day_of_week,
                'number_of_participants' => $schedule->number_of_participants,
                'date' => $schedule->date->format('Y-m-d'),
                'start_time' => $schedule->start_time?->format('H:i:s'),
                'end_time' => $schedule->end_time?->format('H:i:s'),
                'status' => $schedule->status,
                'is_recurring' => $schedule->is_recurring,
                'equipment_needed' => $schedule->equipment_needed ?? [],
            ]);

        return response()->json([
            'data' => $data,
            'meta' => [
                'month' => $validated['month'] ?? $start->format('Y-m'),
                'count' => $schedules->count(), 'total' => $schedules->total(),
                'current_page' => $schedules->currentPage(), 'last_page' => $schedules->lastPage(),
                'per_page' => $schedules->perPage(),
                'start' => $start->toDateString(), 'end' => $end->toDateString(),
            ],
        ]);
    }
}
