<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Equipment;
use App\Models\Building;
use App\Models\College;
use App\Models\Department;
use App\Models\Schedule;
use App\Models\UserAccount;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function getStats()
    {
        return response()->json([
            'stats' => [
                'total_rooms' => Room::count(),
                'available_rooms' => Room::where('status', 'available')->count(),
                'total_equipment' => Equipment::count(),
                'available_equipment' => Equipment::where('status', 'available')->count(),
                'total_buildings' => Building::count(),
                'total_colleges' => College::count(),
                'total_departments' => Department::count(),
                'active_users' => UserAccount::where('account_status', 'active')->count(),
                'today_schedules' => Schedule::where('date', today())->where('status', 'approved')->count(),
                'pending_requests' => Schedule::where('status', 'pending')->count(),
            ],
            'charts' => [
                'room_status' => [
                    'available' => Room::where('status', 'available')->count(),
                    'occupied' => Room::where('status', 'occupied')->count(),
                    'maintenance' => Room::where('status', 'maintenance')->count(),
                    'closed' => Room::where('status', 'closed')->count(),
                ],
                'equipment_status' => [
                    'available' => Equipment::where('status', 'available')->count(),
                    'in_use' => Equipment::where('status', 'in_use')->count(),
                    'maintenance' => Equipment::where('status', 'maintenance')->count(),
                    'retired' => Equipment::where('status', 'retired')->count(),
                ],
                'user_distribution' => UserAccount::select('user_type', \DB::raw('COUNT(*) as count'))
                    ->groupBy('user_type')
                    ->get()
                    ->pluck('count', 'user_type')
                    ->toArray(),
            ]
        ]);
    }

    public function getRooms(Request $request)
    {
        $query = Room::with(['building', 'college', 'department'])
            ->where('status', 'available');

        if ($request->has('building_id')) {
            $query->where('building_id', $request->building_id);
        }

        if ($request->has('college_id')) {
            $query->where('college_id', $request->college_id);
        }

        if ($request->has('min_capacity')) {
            $query->where('capacity', '>=', $request->min_capacity);
        }

        $rooms = $query->orderBy('room_name')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'rooms' => $rooms,
            'buildings' => Building::all(),
            'colleges' => College::all(),
        ]);
    }

    public function search(Request $request)
    {
        $searchTerm = $request->get('q', '');

        if (empty($searchTerm)) {
            return response()->json([
                'results' => [],
                'categories' => [],
            ]);
        }

        $results = [];

        // Search rooms
        $rooms = Room::with(['building', 'college'])
            ->where(function($query) use ($searchTerm) {
                $query->where('room_name', 'like', "%{$searchTerm}%")
                      ->orWhere('room_code', 'like', "%{$searchTerm}%")
                      ->orWhere('location', 'like', "%{$searchTerm}%");
            })
            ->limit(5)
            ->get();

        foreach ($rooms as $room) {
            $results[] = [
                'type' => 'room',
                'id' => $room->id,
                'title' => $room->room_name,
                'subtitle' => $room->room_code,
                'description' => $room->building ? $room->building->building_name : 'No building',
                'status' => $room->status,
                'url' => "/rooms/{$room->id}",
            ];
        }

        // Search equipment
        $equipment = Equipment::with(['room', 'building'])
            ->where(function($query) use ($searchTerm) {
                $query->where('equipment_name', 'like', "%{$searchTerm}%")
                      ->orWhere('inventory_id', 'like', "%{$searchTerm}%")
                      ->orWhere('property_id', 'like', "%{$searchTerm}%")
                      ->orWhere('serial_number', 'like', "%{$searchTerm}%");
            })
            ->limit(5)
            ->get();

        foreach ($equipment as $item) {
            $results[] = [
                'type' => 'equipment',
                'id' => $item->id,
                'title' => $item->equipment_name,
                'subtitle' => $item->inventory_id,
                'description' => $item->location,
                'status' => $item->status,
                'url' => "/equipment/{$item->id}",
            ];
        }

        // Search schedules
        $schedules = Schedule::with(['room'])
            ->where(function($query) use ($searchTerm) {
                $query->where('event_title', 'like', "%{$searchTerm}%")
                      ->orWhere('course_code', 'like', "%{$searchTerm}%")
                      ->orWhere('course_name', 'like', "%{$searchTerm}%")
                      ->orWhere('faculty_name', 'like', "%{$searchTerm}%");
            })
            ->limit(5)
            ->get();

        foreach ($schedules as $schedule) {
            $results[] = [
                'type' => 'schedule',
                'id' => $schedule->id,
                'title' => $schedule->event_title,
                'subtitle' => $schedule->course_code,
                'description' => "{$schedule->date} {$schedule->start_time} - {$schedule->end_time}",
                'status' => $schedule->status,
                'url' => "/schedules/{$schedule->id}",
            ];
        }

        // Search users
        $users = UserAccount::with(['college'])
            ->where(function($query) use ($searchTerm) {
                $query->where('username', 'like', "%{$searchTerm}%")
                      ->orWhere('email', 'like', "%{$searchTerm}%")
                      ->orWhere('first_name', 'like', "%{$searchTerm}%")
                      ->orWhere('last_name', 'like', "%{$searchTerm}%")
                      ->orWhere('employee_id', 'like', "%{$searchTerm}%");
            })
            ->limit(5)
            ->get();

        foreach ($users as $user) {
            $results[] = [
                'type' => 'user',
                'id' => $user->id,
                'title' => $user->full_name,
                'subtitle' => $user->user_type,
                'description' => $user->email,
                'status' => $user->account_status,
                'url' => "/users/{$user->id}",
            ];
        }

        // Group by type for categories
        $categories = [];
        $typeCounts = [];

        foreach ($results as $result) {
            $type = $result['type'];
            if (!isset($typeCounts[$type])) {
                $typeCounts[$type] = 0;
            }
            $typeCounts[$type]++;
        }

        foreach ($typeCounts as $type => $count) {
            $categories[] = [
                'type' => $type,
                'count' => $count,
                'label' => ucfirst($type) . 's',
            ];
        }

        return response()->json([
            'results' => $results,
            'categories' => $categories,
            'total' => count($results),
        ]);
    }
}
