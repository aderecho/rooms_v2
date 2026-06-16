<?php

namespace App\Services;

use App\Models\Room;
use App\Models\Schedule;
use App\Models\Equipment;
use App\Models\UserAccount;
use App\Models\Building;
use Carbon\Carbon;

class ReportService
{
    public function generateRoomUtilizationReport($startDate, $endDate)
    {
        try {
            $rooms = Room::with(['building', 'college'])
                ->withCount(['schedules as total_schedules' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate])
                          ->where('status', 'approved');
                }])
                ->get()
                ->map(function ($room) use ($startDate, $endDate) {
                    $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
                    $utilizationRate = $totalDays > 0 ? ($room->total_schedules / $totalDays) * 100 : 0;

                    return [
                        'room_name' => $room->room_name,
                        'room_code' => $room->room_code,
                        'building' => $room->building?->building_name,
                        'college' => $room->college?->college_name,
                        'capacity' => $room->capacity,
                        'status' => $room->status,
                        'total_schedules' => $room->total_schedules,
                        'utilization_rate' => round($utilizationRate, 2),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'report_type' => 'Room Utilization',
                    'period' => $startDate . ' to ' . $endDate,
                    'generated_at' => now()->toDateTimeString(),
                    'total_rooms' => $rooms->count(),
                    'rooms' => $rooms,
                ],
                'message' => 'Room utilization report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateEquipmentStatusReport()
    {
        try {
            $equipment = Equipment::with(['room', 'building', 'college', 'department', 'assignedUser'])
                ->select('status', \DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(function ($item) {
                    return [
                        'status' => $item->status,
                        'count' => $item->count,
                    ];
                });

            $totalEquipment = Equipment::count();
            $availableEquipment = Equipment::where('status', 'available')->count();
            $inUseEquipment = Equipment::where('status', 'in_use')->count();
            $maintenanceEquipment = Equipment::where('status', 'maintenance')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'report_type' => 'Equipment Status',
                    'generated_at' => now()->toDateTimeString(),
                    'summary' => [
                        'total_equipment' => $totalEquipment,
                        'available' => $availableEquipment,
                        'in_use' => $inUseEquipment,
                        'maintenance' => $maintenanceEquipment,
                    ],
                    'details' => $equipment,
                ],
                'message' => 'Equipment status report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateUserActivityReport($startDate, $endDate)
    {
        try {
            $users = UserAccount::with(['college', 'department'])
                ->whereHas('schedules', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                })
                ->withCount(['schedules as total_schedules' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('date', [$startDate, $endDate]);
                }])
                ->orderBy('total_schedules', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($user) use ($startDate, $endDate) {
                    $lastActivity = Schedule::where('faculty_id', $user->id)
                        ->orWhere('requester_id', $user->id)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    return [
                        'user_id' => $user->id,
                        'full_name' => $user->full_name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'college' => $user->college?->college_name,
                        'department' => $user->department?->department_name,
                        'account_status' => $user->account_status,
                        'total_schedules' => $user->total_schedules,
                        'last_activity' => $lastActivity?->created_at?->format('Y-m-d H:i:s'),
                        'last_login' => $user->last_login_at?->format('Y-m-d H:i:s'),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'report_type' => 'User Activity',
                    'period' => $startDate . ' to ' . $endDate,
                    'generated_at' => now()->toDateTimeString(),
                    'total_users_active' => $users->count(),
                    'users' => $users,
                ],
                'message' => 'User activity report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateScheduleReport($startDate, $endDate)
    {
        try {
            $schedules = Schedule::with(['room', 'faculty', 'requester', 'term'])
                ->whereBetween('date', [$startDate, $endDate])
                ->orderBy('date')
                ->orderBy('start_time')
                ->get()
                ->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'event_title' => $schedule->event_title,
                        'event_type' => $schedule->event_type,
                        'room' => $schedule->room?->room_name,
                        'building' => $schedule->room?->building?->building_name,
                        'faculty' => $schedule->faculty?->full_name,
                        'requester' => $schedule->requester?->full_name,
                        'date' => $schedule->date,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'status' => $schedule->status,
                        'participants' => $schedule->number_of_participants,
                        'course_code' => $schedule->course_code,
                        'course_name' => $schedule->course_name,
                    ];
                });

            $summary = [
                'total_schedules' => $schedules->count(),
                'approved' => $schedules->where('status', 'approved')->count(),
                'pending' => $schedules->where('status', 'pending')->count(),
                'cancelled' => $schedules->where('status', 'cancelled')->count(),
                'by_event_type' => $schedules->groupBy('event_type')->map->count(),
                'by_status' => $schedules->groupBy('status')->map->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'report_type' => 'Schedule Report',
                    'period' => $startDate . ' to ' . $endDate,
                    'generated_at' => now()->toDateTimeString(),
                    'summary' => $summary,
                    'schedules' => $schedules,
                ],
                'message' => 'Schedule report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateBuildingReport()
    {
        try {
            $buildings = Building::with(['college', 'rooms', 'equipment'])
                ->get()
                ->map(function ($building) {
                    return [
                        'id' => $building->id,
                        'building_name' => $building->building_name,
                        'address' => $building->address,
                        'college' => $building->college?->college_name,
                        'total_floors' => $building->total_floors,
                        'total_rooms' => $building->rooms->count(),
                        'available_rooms' => $building->rooms->where('status', 'available')->count(),
                        'occupied_rooms' => $building->rooms->where('status', 'occupied')->count(),
                        'total_equipment' => $building->equipment->count(),
                        'available_equipment' => $building->equipment->where('status', 'available')->count(),
                        'features' => [
                            'has_elevator' => $building->has_elevator,
                            'has_parking' => $building->has_parking,
                            'restroom_count' => $building->restroom_count,
                            'ramp_count' => $building->ramp_count,
                        ],
                    ];
                });

            $summary = [
                'total_buildings' => $buildings->count(),
                'total_rooms' => $buildings->sum('total_rooms'),
                'total_equipment' => $buildings->sum('total_equipment'),
                'buildings_with_elevator' => $buildings->where('features.has_elevator', true)->count(),
                'buildings_with_parking' => $buildings->where('features.has_parking', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'report_type' => 'Building Report',
                    'generated_at' => now()->toDateTimeString(),
                    'summary' => $summary,
                    'buildings' => $buildings,
                ],
                'message' => 'Building report generated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }
}
