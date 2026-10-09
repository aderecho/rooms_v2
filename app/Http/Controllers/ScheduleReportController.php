<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class ScheduleReportController extends Controller
{
    private const TIME_SLOTS = [
        ['start' => '07:30', 'end' => '09:00', 'label' => '7:30–9:00 AM'],
        ['start' => '09:00', 'end' => '10:30', 'label' => '9:00–10:30 AM'],
        ['start' => '10:30', 'end' => '12:00', 'label' => '10:30 AM–12:00 PM'],
        ['start' => '12:00', 'end' => '13:00', 'label' => '12:00–1:00 PM', 'lunch' => true],
        ['start' => '13:00', 'end' => '14:30', 'label' => '1:00–2:30 PM'],
        ['start' => '14:30', 'end' => '16:00', 'label' => '2:30–4:00 PM'],
        ['start' => '16:00', 'end' => '17:30', 'label' => '4:00–5:30 PM'],
        ['start' => '17:30', 'end' => '19:00', 'label' => '5:30–7:00 PM'],
    ];

    private const PAGE_DAYS = [
        ['MONDAY', 'THURSDAY'],
        ['TUESDAY', 'FRIDAY'],
        ['WEDNESDAY'],
    ];

    public function index(Request $request)
    {
        $weekStart = $this->weekStart($request->string('week_start')->toString());
        $rooms = Room::query()->orderBy('room_name')->orderBy('room_code')->get(['id', 'room_name', 'room_code']);
        $selectedRoomIds = $this->selectedRoomIds($request->input('room_ids', []), $rooms);
        $selectedRooms = $rooms->whereIn('id', $selectedRoomIds)->sortBy(
            fn (Room $room) => array_search($room->id, $selectedRoomIds, true)
        )->values();


        return Inertia::render('ScheduleReport', [
            'rooms' => $rooms->map(fn (Room $room) => [
                'id' => $room->id,
                'name' => $room->room_name,
                'code' => $room->room_code,
            ])->values(),
            'selectedRoomIds' => $selectedRoomIds,
            'weekStart' => $weekStart->toDateString(),
            'weekLabel' => $weekStart->format('F j, Y').' – '.$weekStart->addDays(4)->format('F j, Y'),
            'pages' => fn () => ! $request->boolean('generate') ? [] : collect(self::PAGE_DAYS)->map(fn (array $days) => [
                'days' => collect($days)->map(
                    fn (string $day) => $this->dayTable($day, $weekStart, $selectedRooms)
                )->values(),
            ])->values(),
        ]);
    }

    private function weekStart(string $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value ?: 'today')->startOfWeek(CarbonImmutable::MONDAY);
        } catch (\Throwable) {
            return CarbonImmutable::today()->startOfWeek(CarbonImmutable::MONDAY);
        }
    }

    private function selectedRoomIds(mixed $input, Collection $rooms): array
    {
        $requested = collect(is_array($input) ? $input : explode(',', (string) $input))
            ->map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->filter()
            ->unique()
            ->take(4)
            ->values();

        $valid = $requested->filter(fn (int $id) => $rooms->contains('id', $id));
        $fill = $rooms->pluck('id')->reject(fn (int $id) => $valid->contains($id));

        return $valid->concat($fill)->take(4)->values()->all();
    }

    private function dayTable(
        string $day,
        CarbonImmutable $weekStart,
        Collection $rooms
    ): array {
        $dayIndex = array_search($day, ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY'], true);
        $date = $weekStart->addDays($dayIndex);

        return [
            'name' => $day,
            'date' => $date->toDateString(),
            'rooms' => $rooms->map(fn (Room $room) => [
                'id' => $room->id,
                'name' => strtoupper($room->room_name),
            ])->values(),
            'rows' => collect(self::TIME_SLOTS)->map(function (array $slot) use ($rooms, $date) {
                return [
                    'time' => $slot['label'],
                    'cells' => $rooms->map(function (Room $room) use ($slot, $date) {
                        $labels = Schedule::query()
                            ->select('id', 'course_code', 'event_title', 'section', 'number_of_participants', 'start_time', 'end_time')
                            ->where('room_id', $room->id)->whereDate('date', $date->toDateString())
                            ->where('status', '!=', 'cancelled')
                            ->whereTime('start_time', '<', $slot['end'])->whereTime('end_time', '>', $slot['start'])
                            ->orderBy('start_time')->orderBy('id')->lazy(100)
                            ->map(fn (Schedule $schedule) => $this->scheduleLabel($schedule, $slot))->implode(' / ');
                        return [
                            'text' => $labels !== '' ? $labels : (($slot['lunch'] ?? false) ? 'LUNCH BREAK' : 'VACANT'),
                            'occupied' => $labels !== '',
                        ];
                    })->values(),
                ];
            })->values(),
        ];
    }

    private function overlaps(Schedule $schedule, array $slot): bool
    {
        return $this->timeValue($schedule->start_time) < $slot['end']
            && $this->timeValue($schedule->end_time) > $slot['start'];
    }

    private function scheduleLabel(Schedule $schedule, array $slot): string
    {
        $label = $schedule->course_code ?: $schedule->event_title;

        if ($schedule->course_code && $schedule->section) {
            $label .= ' – '.$schedule->section;
        }

        if ($schedule->number_of_participants) {
            $label .= ' ('.$schedule->number_of_participants.')';
        }

        $start = $this->timeValue($schedule->start_time);
        $end = $this->timeValue($schedule->end_time);
        $startsInside = $start > $slot['start'];
        $endsInside = $end < $slot['end'];

        if ($startsInside && $endsInside) {
            return $label.' from '.$this->formatTime($start).' until '.$this->formatTime($end);
        }

        if ($startsInside) {
            return $label.' from '.$this->formatTime($start);
        }

        if ($endsInside) {
            return $label.' until '.$this->formatTime($end);
        }

        return $label;
    }

    private function formatTime(string $time): string
    {
        return CarbonImmutable::createFromFormat('H:i', $time)->format('g:i A');
    }

    private function timeValue(mixed $time): string
    {
        return $time instanceof \DateTimeInterface
            ? $time->format('H:i')
            : substr((string) $time, 0, 5);
    }
}
