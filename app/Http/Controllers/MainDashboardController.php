<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\College;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\ReservationRequest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Schedule;
use App\Models\UserAccount;
use App\Services\RoomAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MainDashboardController extends Controller
{
    public function __construct(
        private readonly RoomAvailabilityService $roomAvailability,
    ) {}

    public function index(Request $request)
    {
        if ($request->user()?->user_type === 'student') {
            return redirect()->route('student.reservations.index');
        }

        // Get statistics for dashboard cards - DYNAMIC COUNTS FROM DATABASE
        $totalAccounts = UserAccount::count(); // Count all user accounts
        $totalDepartments = Department::count(); // Count all departments
        $totalColleges = College::count(); // Count all colleges
        $totalRooms = Room::count(); // Count all rooms
        $totalBuildings = Building::count();
        $totalEquipment = Equipment::count();

        // Get today's date
        $today = now()->format('Y-m-d');
        $latestScheduleDate = Schedule::max('date');
        $calendarAnchor = $latestScheduleDate ? Carbon::parse($latestScheduleDate) : now();
        $calendarStart = $calendarAnchor->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $calendarAnchor->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $todaySchedulesByRoom = Schedule::query()
            ->select(['room_id', 'start_time', 'end_time'])
            ->whereDate('date', $today)
            ->whereNotIn('status', ['cancelled', 'completed', 'rejected'])
            ->orderBy('start_time')
            ->get()
            ->groupBy('room_id');

        // Keep every room schedule available to the room-preview timeline. The
        // dashboard calendar below is still intentionally limited to its visible
        // month, but the preview must not hide the earlier months of a recurring
        // academic-year schedule.
        $rooms = Room::with([
            'college',
            'building',
            'schedules' => function ($query) {
                $query->orderBy('date')
                    ->orderBy('start_time')
                    ->with('faculty');
            },
            'assignedUser',
        ])
            ->orderBy('room_name')
            ->paginate(10);

        // Process rooms data for frontend
        $formatRoom = function ($room) use ($today, $todaySchedulesByRoom) {
            // Get today's schedule for this room
            $todaysSchedule = $room->schedules->first(function ($schedule) use ($today) {
                return $schedule->date->format('Y-m-d') === $today;
            });
            $isFullyOccupied = $this->roomAvailability->isFullyOccupied(
                $todaySchedulesByRoom->get($room->id, collect())
            );
            $isUnavailableByStatus = in_array(
                strtolower((string) $room->status),
                ['maintenance', 'closed'],
                true
            );

            return [
                'id' => $room->id,
                'room_name' => $room->room_name,
                'room_code' => $room->room_code,
                'building_id' => $room->building_id,
                'college_id' => $room->college_id,
                'department_id' => $room->department_id,
                'room_type_id' => $room->room_type_id,
                'assigned_user_id' => $room->assigned_user_id,
                'floor_number' => $room->floor_number,
                'location' => $room->location,
                'description' => $room->description,
                'equipments' => $room->equipments ?? [],
                'college' => $room->college ? [
                    'id' => $room->college->id,
                    'college_name' => $room->college->college_name,
                    'college_code' => $room->college->college_code ?? '',
                ] : null,
                'building' => $room->building ? [
                    'id' => $room->building->id,
                    'building_name' => $room->building->building_name,
                    'building_code' => $room->building->building_code ?? '',
                ] : null,
                'user_account' => $room->assignedUser ? [
                    'id' => $room->assignedUser->id,
                    'username' => $room->assignedUser->username,
                    'full_name' => $room->assignedUser->full_name ?? $room->assignedUser->username,
                    'email' => $room->assignedUser->email ?? '',
                ] : null,
                'status' => $room->status,
                'is_available_for_day' => ! $isUnavailableByStatus && ! $isFullyOccupied,
                'capacity' => $room->capacity,
                'schedules' => $room->schedules->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'room_id' => $schedule->room_id,
                        'cfic_id' => $schedule->cfic_id,
                        'event_title' => $schedule->event_title,
                        'course_code' => $schedule->course_code,
                        'course_name' => $schedule->course_name,
                        'section' => $schedule->section,
                        'faculty_name' => $schedule->faculty_name,
                        'faculty' => $schedule->faculty ? [
                            'id' => $schedule->faculty->id,
                            'full_name' => $schedule->faculty->full_name,
                            'username' => $schedule->faculty->username,
                        ] : null,
                        'date' => $schedule->date->format('Y-m-d'),
                        'day' => $schedule->day_of_week,
                        'start_time' => $schedule->start_time->format('H:i'),
                        'end_time' => $schedule->end_time->format('H:i'),
                        'status' => $schedule->status,
                        'number_of_participants' => $schedule->number_of_participants,
                    ];
                }),
                // For frontend display in table columns
                'today_faculty' => $todaysSchedule ? ($todaysSchedule->faculty_name ?:
                    ($todaysSchedule->faculty ? $todaysSchedule->faculty->full_name : 'N/A')) : 'N/A',
                'today_course' => $todaysSchedule ? ($todaysSchedule->course_name ?: $todaysSchedule->event_title) : 'No Schedule',
                'today_time' => $todaysSchedule ?
                    $todaysSchedule->start_time->format('H:i').' - '.$todaysSchedule->end_time->format('H:i') : 'N/A',
                'today_date' => $today,
            ];
        };

        $processedRooms = $rooms->getCollection()->map($formatRoom);

        // Set the processed collection back to the paginator
        $rooms->setCollection($processedRooms);

        $allRooms = Room::with([
            'college',
            'building',
            'schedules' => function ($query) {
                $query->orderBy('date')
                    ->orderBy('start_time')
                    ->with('faculty');
            },
            'assignedUser',
        ])
            ->orderBy('room_name')
            ->get()
            ->map($formatRoom);

        // Get today's schedules count
        $todaySchedulesCount = Schedule::where('date', $today)->count();

        // Get pending schedules count
        $pendingSchedulesCount = Schedule::where('status', 'pending')->count();
        $pendingReservationRequestsCount = ReservationRequest::where('status', 'pending')->count();

        $calendarSchedules = Schedule::with(['room.college', 'room.building', 'faculty'])
            ->whereBetween('date', [$calendarStart->format('Y-m-d'), $calendarEnd->format('Y-m-d')])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'room_id' => $schedule->room_id,
                    'room_name' => $schedule->room?->room_name,
                    'room_code' => $schedule->room?->room_code,
                    'college_name' => $schedule->room?->college?->college_name,
                    'building_name' => $schedule->room?->building?->building_name,
                    'event_title' => $schedule->event_title,
                    'course_code' => $schedule->course_code,
                    'course_name' => $schedule->course_name,
                    'faculty_name' => $schedule->faculty_name ?: $schedule->faculty?->full_name,
                    'date' => $schedule->date->format('Y-m-d'),
                    'day' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time->format('H:i'),
                    'end_time' => $schedule->end_time->format('H:i'),
                    'status' => $schedule->status,
                    'number_of_participants' => $schedule->number_of_participants,
                ];
            });

        // Get equipment statistics
        $equipmentStats = [
            'total' => Equipment::count(),
            'available' => Equipment::where('status', 'available')->count(),
            'in_use' => Equipment::where('status', 'in_use')->count(),
            'maintenance' => Equipment::where('status', 'maintenance')->count(),
        ];

        // Get building statistics
        $buildingStats = [
            'total' => Building::count(),
            'with_elevator' => Building::where('has_elevator', true)->count(),
            'with_parking' => Building::where('has_parking', true)->count(),
        ];

        // Get user statistics by type
        $userStats = UserAccount::select('user_type', \DB::raw('COUNT(*) as count'))
            ->groupBy('user_type')
            ->get()
            ->pluck('count', 'user_type')
            ->toArray();

        return inertia('MainDashboard', [
            // DYNAMIC CARD DATA - These will be automatically updated based on database records
            'totalAccounts' => $totalAccounts,
            'totalDepartments' => $totalDepartments,
            'totalColleges' => $totalColleges,
            'totalRooms' => $totalRooms,
            'totalBuildings' => $totalBuildings,
            'totalEquipment' => $totalEquipment,
            'todaySchedules' => $todaySchedulesCount,
            'pendingSchedules' => $pendingSchedulesCount,
            'pendingReservationRequests' => $pendingReservationRequestsCount,
            'rooms' => $rooms,
            'allRooms' => $allRooms,
            'equipmentStats' => $equipmentStats,
            'buildingStats' => $buildingStats,
            'userStats' => $userStats,
            'todayDate' => $today,
            'availabilityDate' => $today,
            'availabilityOpeningTime' => RoomAvailabilityService::OPENING_TIME,
            'availabilityClosingTime' => RoomAvailabilityService::CLOSING_TIME,
            'recentActivities' => $this->getRecentActivities(),
            'calendarSchedules' => $calendarSchedules,
            'calendarStart' => $calendarStart->format('Y-m-d'),
            'calendarEnd' => $calendarEnd->format('Y-m-d'),
            'calendarMonthLabel' => $calendarAnchor->format('F Y'),
            'calendarActiveMonth' => (int) $calendarAnchor->format('n') - 1,
            'calendarActiveYear' => (int) $calendarAnchor->format('Y'),
            'buildings' => Building::orderBy('building_name')->get(),
            'colleges' => College::orderBy('college_name')->get(),
            'departments' => Department::orderBy('department_name')->get(),
            'roomTypes' => RoomType::orderBy('room_type_name')->get(),
            'users' => UserAccount::orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }

    private function getRecentActivities()
    {
        $activities = [];

        // Get recent room changes
        $recentRooms = Room::with('college')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentRooms as $room) {
            $activities[] = [
                'type' => 'room',
                'action' => 'updated',
                'title' => "Room {$room->room_name} updated",
                'description' => 'Room information was modified',
                'time' => $room->updated_at->diffForHumans(),
                'color' => 'blue',
                'icon' => 'door-open',
            ];
        }

        // Get recent schedule bookings
        $recentSchedules = Schedule::with('room')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentSchedules as $schedule) {
            $activities[] = [
                'type' => 'schedule',
                'action' => $schedule->status,
                'title' => "New schedule: {$schedule->event_title}",
                'description' => "Scheduled for {$schedule->date->format('M d, Y')} at {$schedule->room->room_name}",
                'time' => $schedule->created_at->diffForHumans(),
                'color' => $schedule->status === 'approved' ? 'green' : 'yellow',
                'icon' => 'calendar',
            ];
        }

        // Get recent user logins
        $recentLogins = UserAccount::whereNotNull('last_login_at')
            ->orderBy('last_login_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentLogins as $user) {
            $activities[] = [
                'type' => 'user',
                'action' => 'logged_in',
                'title' => "{$user->username} logged in",
                'description' => 'User accessed the system',
                'time' => $user->last_login_at->diffForHumans(),
                'color' => 'purple',
                'icon' => 'user',
            ];
        }

        // Sort by time
        usort($activities, function ($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return array_slice($activities, 0, 10);
    }

    public function search(Request $request)
    {
        $search = $request->get('search', '');
        $today = now()->format('Y-m-d');

        $rooms = Room::with([
            'college',
            'building',
            'schedules' => function ($query) use ($today) {
                $query->where('date', $today)
                    ->orderBy('start_time')
                    ->with('faculty');
            },
            'assignedUser',
        ])
            ->where(function ($query) use ($search) {
                $query->where('room_name', 'like', "%{$search}%")
                    ->orWhere('room_code', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('college', function ($q) use ($search) {
                        $q->where('college_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('building', function ($q) use ($search) {
                        $q->where('building_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('schedules', function ($q) use ($search) {
                        $q->where('event_title', 'like', "%{$search}%")
                            ->orWhere('course_name', 'like', "%{$search}%")
                            ->orWhere('faculty_name', 'like', "%{$search}%")
                            ->orWhere('course_code', 'like', "%{$search}%");
                    });
            })
            ->orderBy('room_name')
            ->paginate(10);

        $processedRooms = $rooms->getCollection()->map(function ($room) use ($today) {
            $todaysSchedule = $room->schedules->first();

            return [
                'id' => $room->id,
                'room_name' => $room->room_name,
                'room_code' => $room->room_code,
                'location' => $room->location,
                'college' => $room->college ? [
                    'id' => $room->college->id,
                    'college_name' => $room->college->college_name,
                ] : null,
                'building' => $room->building ? [
                    'id' => $room->building->id,
                    'building_name' => $room->building->building_name,
                ] : null,
                'user_account' => $room->assignedUser ? [
                    'id' => $room->assignedUser->id,
                    'username' => $room->assignedUser->username,
                ] : null,
                'status' => $room->status,
                'capacity' => $room->capacity,
                'schedules' => $room->schedules->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'cfic_id' => $schedule->cfic_id,
                        'event_title' => $schedule->event_title,
                        'course_code' => $schedule->course_code,
                        'course_name' => $schedule->course_name,
                        'faculty_name' => $schedule->faculty_name,
                        'faculty' => $schedule->faculty,
                        'date' => $schedule->date->format('Y-m-d'),
                        'day' => $schedule->day_of_week,
                        'start_time' => $schedule->start_time->format('H:i'),
                        'end_time' => $schedule->end_time->format('H:i'),
                        'status' => $schedule->status,
                        'number_of_participants' => $schedule->number_of_participants,
                    ];
                }),
                'today_faculty' => $todaysSchedule ? ($todaysSchedule->faculty_name ?:
                    ($todaysSchedule->faculty ? $todaysSchedule->faculty->full_name : 'N/A')) : 'N/A',
                'today_course' => $todaysSchedule ? ($todaysSchedule->course_name ?: $todaysSchedule->event_title) : 'No Schedule',
                'today_time' => $todaysSchedule ?
                    $todaysSchedule->start_time->format('H:i').' - '.$todaysSchedule->end_time->format('H:i') : 'N/A',
                'today_date' => $today,
            ];
        });

        $rooms->setCollection($processedRooms);

        return response()->json([
            'rooms' => $rooms,
            'search' => $search,
            'total' => $rooms->total(),
        ]);
    }
}
