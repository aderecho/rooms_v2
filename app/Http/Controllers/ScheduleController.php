<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Room;
use App\Models\Term;
use Inertia\Inertia;
use App\Models\UserAccount;
use App\Models\Schedule;
use App\Services\EquipmentInventoryService;
use App\Services\ScheduleNotificationService;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly EquipmentInventoryService $equipmentInventory,
        private readonly ScheduleNotificationService $notificationService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:200',
            'per_page' => 'nullable|integer|in:5,10,20,50',
            'page' => 'nullable|integer|min:1',
        ]);
        $search = trim($filters['search'] ?? '');
        $schedules = $this->applySearch($this->pageQuery(), $search)
            ->orderBy('date', 'desc')->orderBy('start_time', 'desc')->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 10))->withQueryString();
        $counts = Schedule::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $pendingCount = $this->applySearch(Schedule::query(), $search)->where('status', 'pending')->count();

        $rooms = $this->roomsWithEquipmentDetails(Room::select('id', 'room_name', 'room_code', 'equipments')->orderBy('room_name')->get());
        $faculty = UserAccount::select('id', 'first_name', 'middle_name', 'last_name')->where('user_type', 'faculty')->get();
        $requesters = UserAccount::select('id', 'first_name', 'middle_name', 'last_name')->whereIn('user_type', ['faculty', 'staff'])->get();
        $terms = Term::select('id', 'term_name')->where('status', 'active')->get();

        $sessionUsername = data_get($request->session()->get('user'), 'username');
        $currentUser = $sessionUsername
            ? UserAccount::where('username', $sessionUsername)->first()
            : null;
        $currentRequester = $currentUser
            ? trim(implode(' ', array_filter([
                $currentUser->first_name,
                $currentUser->middle_name,
                $currentUser->last_name,
            ])))
            : ($sessionUsername ?: '');
        $currentUserRole = strtolower((string) data_get($request->session()->get('user'), 'role', ''));

        return Inertia::render('Schedule', [
            'schedules' => $schedules->items(),
            'schedulePagination' => [
                'current_page' => $schedules->currentPage(), 'last_page' => $schedules->lastPage(),
                'per_page' => $schedules->perPage(), 'total' => $schedules->total(),
                'from' => $schedules->firstItem(), 'to' => $schedules->lastItem(),
            ],
            'scheduleFilters' => ['search' => $search],
            'scheduleCounts' => $counts,
            'matchingPendingCount' => $pendingCount,
            'rooms' => $rooms,
            'roomEquipmentQuantities' => $this->buildRoomEquipmentQuantitiesMap($rooms),
            'globalEquipmentQuantities' => $this->equipmentInventory->globalInventoryCountsByName(),
            'faculty' => $faculty,
            'requesters' => $requesters,
            'currentRequester' => $currentRequester,
            'currentUserRole' => $currentUserRole,
            'terms' => $terms,
        ]);
    }

    private function pageQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Schedule::query()
            ->select([
                'id', 'room_id', 'event_title', 'event_type', 'course_code', 'course_name',
                'section', 'faculty_name', 'faculty_id', 'requester_id', 'requester_name',
                'date', 'start_time', 'end_time', 'number_of_participants', 'description',
                'agenda', 'organizer', 'equipment_needed', 'additional_requirements',
                'status', 'is_recurring',
            ])
            ->with([
                'room:id,room_name,room_code,building_id,college_id',
                'room.building:id,building_name',
                'room.college:id,college_name',
                'faculty:id,first_name,middle_name,last_name',
                'requester:id,first_name,middle_name,last_name',
            ]);
    }

    private function applySearch(\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder
    {
        if ($search === '') {
            return $query;
        }
        $term = '%'.strtolower($search).'%';
        return $query->where(function ($q) use ($term) {
            foreach (['event_title', 'course_code', 'course_name', 'faculty_name', 'requester_name', 'description', 'date', 'status', 'event_type'] as $field) {
                $q->orWhereRaw('LOWER('.$field.') LIKE ?', [$term]);
            }
            $q->orWhereHas('room', function ($room) use ($term) {
                $room->whereRaw('LOWER(room_name) LIKE ?', [$term])->orWhereRaw('LOWER(room_code) LIKE ?', [$term])
                    ->orWhereHas('building', fn ($building) => $building->whereRaw('LOWER(building_name) LIKE ?', [$term]))
                    ->orWhereHas('college', fn ($college) => $college->whereRaw('LOWER(college_name) LIKE ?', [$term]));
            });
        });
    }

    public function showDetails(Schedule $schedule)
    {
        return response()->json($this->pageQuery()->findOrFail($schedule->id));
    }

    public function calendarData(Request $request)
    {
        $validated = $request->validate([
            'start' => 'required|date_format:Y-m-d',
            'end' => 'required|date_format:Y-m-d|after_or_equal:start',
            'page' => 'nullable|integer|min:1',
        ]);
        abort_if(Carbon::parse($validated['start'])->diffInDays(Carbon::parse($validated['end'])) > 62, 422, 'Calendar range cannot exceed 62 days.');
        return response()->json($this->pageQuery()->whereBetween('date', [$validated['start'], $validated['end']])
            ->orderBy('date')->orderBy('start_time')->orderBy('id')->paginate(500));
    }

    /**
     * Attach equipment_details [{ name, quantity }] to each room for the appointment form.
     */
    private function roomsWithEquipmentDetails($rooms)
    {
        return $rooms->map(function (Room $room) {
            $names = is_array($room->equipments) ? array_map(fn ($item) => is_array($item) ? ($item['name'] ?? '') : $item, $room->equipments) : [];
            $room->setAttribute(
                'equipment_details',
                $this->equipmentInventory->equipmentDetailsForRoom($room->id, $names)
            );

            return $room;
        });
    }

    private function buildRoomEquipmentQuantitiesMap($rooms): array
    {
        $map = [];

        foreach ($rooms as $room) {
            $details = $room->equipment_details ?? [];
            if (! is_array($details) || $details === []) {
                continue;
            }

            $entries = [];
            foreach ($details as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $entries[strtolower($name)] = (int) ($item['quantity'] ?? 0);
            }

            foreach (array_filter([
                $room->room_name,
                $room->room_code,
                strtolower((string) $room->room_name),
                strtolower((string) $room->room_code),
            ]) as $alias) {
                $map[$alias] = $entries;
            }
        }

        return $map;
    }

    /**
     * Equipment list with available quantities for the appointment form.
     */
    public function roomEquipment(Request $request)
    {
        $roomName = trim((string) $request->query('room', ''));
        if ($roomName === '') {
            return response()->json([
                'success' => false,
                'message' => 'Room is required.',
                'equipment' => [],
            ], 422);
        }

        $room = Room::query()
            ->where('room_name', $roomName)
            ->orWhere('room_code', $roomName)
            ->first();

        $names = is_array($room?->equipments) ? array_map(fn ($item) => is_array($item) ? ($item['name'] ?? '') : $item, $room->equipments) : [];

        return response()->json([
            'success' => true,
            'equipment' => $this->equipmentInventory->equipmentDetailsForRoom($room?->id, $names),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);
        $payload = $this->buildSchedulePayload($validated, $request);

        if ($conflict = $this->findConflict($payload)) {
            return response()->json([
                'success' => false,
                'message' => $this->formatConflictMessage($conflict),
                'conflict' => $conflict,
            ], 422);
        }

        $schedule = Schedule::create($payload);
        $schedule->load(['room.building', 'room.college', 'faculty', 'requester', 'term']);

        $currentUser = $this->notificationService->resolveCurrentUser($request);
        if ($currentUser && !$schedule->requester_id) {
            $schedule->update(['requester_id' => $currentUser->id]);
            $schedule->load(['requester']);
        }
        $this->notificationService->notifyAdminsOfNewAppointment($schedule, $currentUser);
        if ($currentUser) {
            $this->notificationService->notifyBookingConfirmation($schedule, $currentUser);
        }

        return response()->json([
            'success' => true,
            'schedule' => $schedule,
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $validated = $this->validatePayload($request);
        $payload = $this->buildSchedulePayload($validated, $request);

        if ($conflict = $this->findConflict($payload, $schedule->id)) {
            return response()->json([
                'success' => false,
                'message' => $this->formatConflictMessage($conflict),
                'conflict' => $conflict,
            ], 422);
        }

        $schedule->update($payload);
        $schedule->load(['room.building', 'room.college', 'faculty', 'requester', 'term']);

        return response()->json([
            'success' => true,
            'schedule' => $schedule,
        ]);
    }

    private function findConflict(array $payload, ?int $ignoreId = null)
    {
        $query = Schedule::query()
            ->where('room_id', $payload['room_id'])
            ->where('date', $payload['date'])
            ->where('start_time', '<', $payload['end_time'])
            ->where('end_time', '>', $payload['start_time']);

        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        return $query->with('room')->first();
    }

    private function formatConflictMessage(Schedule $conflict): string
    {
        $roomName = $conflict->room?->room_name ?? $conflict->room?->room_code ?? 'this room';
        $start = Carbon::parse($conflict->start_time)->format('g:i A');
        $end = Carbon::parse($conflict->end_time)->format('g:i A');
        $date = Carbon::parse($conflict->date)->format('l, F j, Y');
        $title = $conflict->event_title ?: 'an existing appointment';

        return "Booking conflict: {$roomName} is already reserved on {$date} from {$start} to {$end} for \"{$title}\". Please choose a different room or time.";
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function updateStatus(Request $request, Schedule $schedule)
    {
        $role = strtolower((string) data_get($request->session()->get('user'), 'role', ''));
        if ($role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Only admin accounts can update appointment status.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:pending,in_progress,approved,completed,rejected,cancelled',
        ]);

        if (in_array(strtolower((string) $schedule->status), ['completed', 'rejected', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'This appointment is closed and its status can no longer be changed.',
            ], 422);
        }

        if ($validated['status'] === 'approved') {
            $schedule = app(\App\Services\ScheduleApprovalService::class)->approve(
                $schedule, $this->notificationService->resolveCurrentUser($request)
            );
            $schedule->load(['room.building', 'room.college', 'faculty', 'requester', 'term']);
            return response()->json(['success' => true, 'schedule' => $schedule]);
        }

        $schedule->update([
            'status' => $validated['status'],
        ]);

        $schedule->load(['room.building', 'room.college', 'faculty', 'requester', 'term']);

        $changedBy = $this->notificationService->resolveCurrentUser($request);
        $this->notificationService->notifyStatusChangeToAllRoles(
            $schedule,
            $validated['status'],
            $changedBy
        );

        return response()->json([
            'success' => true,
            'schedule' => $schedule,
        ]);
    }

    public function bulkApprove(Request $request)
    {
        abort_unless(strtolower((string) data_get($request->session()->get('user'), 'role', '')) === 'admin', 403);
        $validated = $request->validate([
            'all' => 'sometimes|boolean',
            'search' => 'nullable|string|max:200',
            'ids' => 'required_unless:all,true|array|min:1|max:5000',
            'ids.*' => 'required|integer|distinct|exists:schedules,id',
        ]);
        $admin = $this->notificationService->resolveCurrentUser($request);
        $counts = ['approved_count' => 0, 'rejected_count' => 0];
        $query = Schedule::query();
        if ($request->boolean('all')) {
            $this->applySearch($query, trim($validated['search'] ?? ''));
        } else {
            $query->whereIn('id', $validated['ids']);
        }
        // Freeze the upper ID so records created during this operation are not included.
        $query->where('id', '<=', (int) Schedule::max('id'));
        $query->chunkById(100, function ($schedules) use ($admin, &$counts) {
            foreach ($schedules as $schedule) {
                if ($schedule->status === 'rejected') {
                    continue;
                }
                if ($schedule->status !== 'pending') {
                    continue;
                }
                $result = app(\App\Services\ScheduleApprovalService::class)->approve($schedule, $admin);
                $counts[$result->status === 'approved' ? 'approved_count' : 'rejected_count']++;
            }
        });
        return response()->json(['success' => true, ...$counts]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'room' => 'required|string',
            'type' => 'required|string',
            'title' => 'required|string',
            'date' => 'required|date_format:Y-m-d',
            'startTime' => 'required|date_format:H:i:s',
            'endTime' => 'required|date_format:H:i:s',
            'start' => 'nullable|string',
            'end' => 'nullable|string',
            'numberParticipants' => 'nullable|integer',
            'numberOfStudents' => 'nullable|integer',
            'deptOffice' => 'nullable|string',
            'organization' => 'nullable|string',
            'description' => 'nullable|string',
            'agenda' => 'nullable|string',
            'requester' => 'nullable|string',
            'subject' => 'nullable|string',
            'section' => 'nullable|string',
            'faculty' => 'nullable|string',
            'organizer' => 'nullable|string',
            'name' => 'nullable|string',
            'tablesChairs' => 'nullable|boolean',
            'airConditioner' => 'nullable|boolean',
            'whiteboard' => 'nullable|boolean',
            'equipmentNeeded' => 'nullable|array',
            'equipmentNeeded.*' => 'string|max:255',
            'additionalInstructions' => 'nullable|string',
            'recurring' => 'nullable|boolean',
        ]);
    }

    private function buildSchedulePayload(array $validated, Request $request): array
    {
        $room = Room::where('room_name', $validated['room'])->first()
            ?? Room::where('room_code', $validated['room'])->first()
            ?? Room::first();

        $eventTypeMap = [
            'Class' => 'class',
            'Meeting' => 'meeting',
            'Event' => 'event',
            'Other type of activity' => 'other',
        ];

        $equipment = [];
        if (!empty($validated['equipmentNeeded']) && is_array($validated['equipmentNeeded'])) {
            $equipment = array_values(array_filter(array_map(
                fn($item) => trim((string) $item),
                $validated['equipmentNeeded']
            )));
        } else {
            if ($request->boolean('tablesChairs')) $equipment[] = 'Tables and chairs';
            if ($request->boolean('airConditioner')) $equipment[] = 'Air conditioner';
            if ($request->boolean('whiteboard')) $equipment[] = 'Whiteboard';
        }

        $additional = [];
        if (!empty($validated['additionalInstructions'])) {
            $additional[] = $validated['additionalInstructions'];
        }

        return [
            'room_id' => $room?->id ?? 1,
            'event_title' => $validated['title'],
            'event_type' => $eventTypeMap[$validated['type']] ?? 'other',
            'course_code' => $validated['subject'] ?? null,
            'course_name' => $validated['subject'] ?? null,
            'section' => $validated['section'] ?? null,
            'faculty_name' => $validated['faculty'] ?? null,
            'date' => $validated['date'],
            'start_time' => $validated['startTime'],
            'end_time' => $validated['endTime'],
            'day_of_week' => Carbon::parse($validated['date'])->englishDayOfWeek,
            'number_of_participants' => $validated['numberParticipants']
                ?? $validated['numberOfStudents']
                ?? null,
            'requester_name' => $validated['requester'] ?? null,
            'description' => $validated['description'] ?? null,
            'agenda' => $validated['agenda'] ?? null,
            'organizer' => $validated['organizer']
                ?? $validated['deptOffice']
                ?? null,
            'equipment_needed' => $equipment ?: null,
            'additional_requirements' => $additional ?: null,
            'status' => 'pending',
            'is_recurring' => $request->boolean('recurring'),
        ];
    }
}
