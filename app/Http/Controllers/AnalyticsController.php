<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\UserAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $totalRooms = Room::count();
        $totalBuildings = Building::count();
        $totalUsers = UserAccount::count();
        $totalSchedules = Schedule::count();

        return Inertia::render('Analytics', [
            'summary' => [
                [
                    'label' => 'Rooms',
                    'value' => $totalRooms,
                    'note' => 'Managed spaces',
                    'accent' => 'green',
                ],
                [
                    'label' => 'Buildings',
                    'value' => $totalBuildings,
                    'note' => 'Campus facilities',
                    'accent' => 'teal',
                ],
                [
                    'label' => 'Users',
                    'value' => $totalUsers,
                    'note' => 'System accounts',
                    'accent' => 'indigo',
                ],
                [
                    'label' => 'Schedules',
                    'value' => $totalSchedules,
                    'note' => 'Booking records',
                    'accent' => 'slate',
                ],
            ],
            'roomStatus' => $this->countBy(Room::query(), 'status'),
            'userDistribution' => $this->countBy(UserAccount::query(), 'user_type'),
            'scheduleStatus' => $this->countBy(Schedule::query(), 'status'),
            'roomsByBuilding' => Building::query()
                ->withCount('rooms')
                ->orderByDesc('rooms_count')
                ->orderBy('building_name')
                ->take(8)
                ->get(['id', 'building_name'])
                ->map(fn (Building $building) => [
                    'label' => $building->building_name,
                    'value' => (int) $building->rooms_count,
                ])
                ->values(),
            'topRooms' => Room::query()
                ->withCount('schedules')
                ->with('building:id,building_name')
                ->orderByDesc('schedules_count')
                ->orderBy('room_name')
                ->take(8)
                ->get(['id', 'room_name', 'room_code', 'building_id'])
                ->map(fn (Room $room) => [
                    'label' => trim("{$room->room_name} ({$room->room_code})"),
                    'meta' => $room->building?->building_name ?? 'No building assigned',
                    'value' => (int) $room->schedules_count,
                ])
                ->values(),
            'scheduleWindow' => [
                'today' => Schedule::query()->whereDate('date', $today)->count(),
                'upcoming' => Schedule::query()->whereDate('date', '>=', $today)->count(),
                'approvedUpcoming' => Schedule::query()
                    ->whereDate('date', '>=', $today)
                    ->where('status', 'approved')
                    ->count(),
            ],
            'updatedAt' => now()->format('M d, Y h:i A'),
        ]);
    }

    private function countBy(Builder $query, string $column)
    {
        return $query
            ->selectRaw("COALESCE(NULLIF({$column}, ''), 'unspecified') as label, COUNT(*) as value")
            ->groupBy($column)
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row) => [
                'label' => $this->formatLabel((string) $row->label),
                'value' => (int) $row->value,
            ])
            ->values();
    }

    private function formatLabel(string $value): string
    {
        return ucwords(str_replace('_', ' ', strtolower($value)));
    }
}
