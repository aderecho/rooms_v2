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
        $schedules = Schedule::with(['room.building', 'room.college', 'faculty', 'requester', 'term'])
            ->orderBy('date', 'desc')
            ->orderBy('start_time', 'desc')
            ->get();

        $rooms = $this->roomsWithEquipmentDetails(Room::orderBy('room_name')->get());
        $faculty = UserAccount::where('user_type', 'faculty')->get();
        $requesters = UserAccount::whereIn('user_type', ['faculty', 'staff'])->get();
        $terms = Term::where('status', 'active')->get();

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
            'schedules' => $schedules,
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

    /**
     * Attach equipment_details [{ name, quantity }] to each room for the appointment form.
     */
    private function roomsWithEquipmentDetails($rooms)
    {
        return $rooms->map(function (Room $room) {
            $names = is_array($room->equipments) ? $room->equipments : [];
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

        $names = is_array($room?->equipments) ? $room->equipments : [];

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
