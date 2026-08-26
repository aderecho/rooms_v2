<?php

namespace App\Http\Controllers\API;

use App\Models\Room;
use App\Models\Schedule;
use App\Services\ScheduleDateParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class RoomController
{
    public function __construct(
        private readonly ScheduleDateParser $scheduleDateParser,
    ) {}

    public function index()
    {
        $rooms = Room::all();
        /**
         * return all rooms [room_name, room_code, capacity]
         */

        return response()->json([
            'rooms' => $rooms,
        ]);
    }

    public function createSchedule(Request $request)
    {
        if ($request->filled('room_id') && $request->filled('room_name')) {
            throw ValidationException::withMessages([
                'room_id' => ['Provide either room_id or room_name, not both.'],
                'room_name' => ['Provide either room_id or room_name, not both.'],
            ]);
        }

        $validated = $request->validate([
            'room_id' => ['nullable', 'required_without:room_name', 'integer', 'exists:rooms,id'],
            'room_name' => ['nullable', 'required_without:room_id', 'string', 'exists:rooms,room_name'],
            'schedule' => ['required', 'array'],
            'schedule.event_title' => ['required', 'string', 'max:255'],
            'schedule.event_type' => ['nullable', 'string', 'in:class,meeting,event,other'],
            'schedule.date' => ['required', 'string'],
            'schedule.start_time' => ['nullable', 'date_format:H:i'],
            'schedule.end_time' => ['nullable', 'date_format:H:i', 'after:schedule.start_time'],
            'schedule.course_code' => ['nullable', 'string', 'max:255'],
            'schedule.course_name' => ['nullable', 'string', 'max:255'],
            'schedule.section' => ['nullable', 'string', 'max:255'],
            'schedule.faculty_name' => ['nullable', 'string', 'max:255'],
            'schedule.number_of_participants' => ['nullable', 'integer', 'min:1'],
            'schedule.requester_name' => ['nullable', 'string', 'max:255'],
            'schedule.description' => ['nullable', 'string'],
            'schedule.agenda' => ['nullable', 'string', 'max:255'],
            'schedule.organizer' => ['nullable', 'string', 'max:255'],
            'schedule.equipment_needed' => ['nullable', 'array'],
            'schedule.equipment_needed.*' => ['string', 'max:255'],
            'schedule.additional_requirements' => ['nullable', 'array'],
            'schedule.additional_requirements.*' => ['string', 'max:255'],
            'schedule.cfic_id' => ['nullable', 'string', 'max:100'],
        ]);

        $room = isset($validated['room_id'])
            ? Room::findOrFail($validated['room_id'])
            : Room::where('room_name', $validated['room_name'])->firstOrFail();

        $scheduleData = $validated['schedule'];

        try {
            $parsedDate = $this->scheduleDateParser->parse(
                $scheduleData['date'],
                $scheduleData['start_time'] ?? null,
                $scheduleData['end_time'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'schedule.date' => [$exception->getMessage()],
            ]);
        }

        $result = DB::transaction(function () use ($room, $scheduleData, $parsedDate) {
            Room::whereKey($room->id)->lockForUpdate()->firstOrFail();

            $conflict = Schedule::query()
                ->where('room_id', $room->id)
                ->whereIn(DB::raw('DATE(date)'), $parsedDate['dates'])
                ->whereNotIn('status', ['cancelled', 'completed', 'rejected'])
                ->where('start_time', '<', $parsedDate['end_time'])
                ->where('end_time', '>', $parsedDate['start_time'])
                ->orderBy('date')
                ->orderBy('start_time')
                ->first();

            if ($conflict) {
                return ['conflict' => $conflict];
            }

            $baseSchedule = collect($scheduleData)
                ->except(['date', 'start_time', 'end_time'])
                ->all();
            $schedules = collect($parsedDate['dates'])->map(function (string $date) use ($baseSchedule, $parsedDate, $room) {
                return Schedule::create([
                    ...$baseSchedule,
                    'room_id' => $room->id,
                    'event_type' => $baseSchedule['event_type'] ?? 'other',
                    'date' => $date,
                    'start_time' => $parsedDate['start_time'],
                    'end_time' => $parsedDate['end_time'],
                    'day_of_week' => Carbon::parse($date)->englishDayOfWeek,
                    'status' => 'pending',
                    'is_recurring' => $parsedDate['is_recurring'],
                    'recurrence_pattern' => $parsedDate['recurrence_pattern'],
                ]);
            });

            return ['schedules' => $schedules];
        });

        if (isset($result['conflict'])) {
            $conflict = $result['conflict'];

            return response()->json([
                'success' => false,
                'message' => 'The room already has a schedule that overlaps the requested date and time.',
                'conflict' => [
                    'id' => $conflict->id,
                    'event_title' => $conflict->event_title,
                    'date' => $conflict->date->format('Y-m-d'),
                    'start_time' => $conflict->start_time->format('H:i'),
                    'end_time' => $conflict->end_time->format('H:i'),
                    'status' => $conflict->status,
                ],
            ], 409);
        }

        $schedules = $result['schedules']->each(
            fn (Schedule $schedule) => $schedule->setRelation('room', $room)
        );

        return response()->json([
            'success' => true,
            'message' => $schedules->count() === 1
                ? 'Schedule created successfully.'
                : "{$schedules->count()} recurring schedules created successfully.",
            'schedule_count' => $schedules->count(),
            'schedule' => $schedules->first(),
            'schedules' => $schedules,
        ], 201);
    }
}
