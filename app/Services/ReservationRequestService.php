<?php

namespace App\Services;

use App\Models\ReservationRequest;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\UserAccount;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationRequestService
{
    private const BLOCKING_SCHEDULE_STATUSES = ['approved', 'in_progress'];

    private const BLOCKING_REQUEST_STATUSES = [
        ReservationRequest::STATUS_PENDING,
        ReservationRequest::STATUS_APPROVED,
    ];

    public function __construct(
        private readonly ReservationNotificationService $notifications,
    ) {}

    public function availability(string $date, string $startTime, string $endTime): array
    {
        $this->assertWithinOperatingHours($startTime, $endTime);

        $rooms = Room::query()
            ->with(['building', 'college'])
            ->orderBy('room_name')
            ->get();

        return $rooms->map(function (Room $room) use ($date, $startTime, $endTime) {
            $conflicts = $this->roomConflicts($room->id, $date, $startTime, $endTime);
            $statusAvailable = strtolower((string) $room->status) === 'available';

            return [
                'id' => $room->id,
                'room_name' => $room->room_name,
                'room_code' => $room->room_code,
                'capacity' => $room->capacity,
                'location' => $room->location,
                'building' => $room->building?->building_name,
                'college' => $room->college?->college_name,
                'status' => $room->status,
                'is_available' => $statusAvailable && $conflicts === [],
                'unavailable_reason' => $statusAvailable ? null : 'Room is currently unavailable.',
                'conflicts' => $conflicts,
            ];
        })->values()->all();
    }

    public function submit(UserAccount $student, array $data): ReservationRequest
    {
        [$request, $admins] = DB::transaction(function () use ($student, $data) {
            $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);
            $this->assertWithinOperatingHours($data['start_time'], $data['end_time']);
            $this->assertRoomCanAccept($room, (int) $data['attendees']);
            $this->assertStudentHasNoOverlap($student->id, $data);
            $this->assertRoomHasNoConflict($room->id, $data);

            $request = ReservationRequest::create([
                ...$data,
                'student_id' => $student->id,
                'status' => ReservationRequest::STATUS_PENDING,
            ])->load(['student', 'room.building', 'room.college']);

            $admins = $this->notifications->notifyAdminsInSystem($request);

            return [$request, $admins];
        }, 3);

        $this->notifications->queueAdminEmails($admins, $request);

        return $request;
    }

    public function approve(ReservationRequest $reservationRequest, UserAccount $admin): ReservationRequest
    {
        $request = DB::transaction(function () use ($reservationRequest, $admin) {
            $lockedRequest = ReservationRequest::query()
                ->lockForUpdate()
                ->findOrFail($reservationRequest->id);

            if ($lockedRequest->status !== ReservationRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending reservation requests can be approved.',
                ]);
            }

            $room = Room::query()->lockForUpdate()->findOrFail($lockedRequest->room_id);
            $data = [
                'reservation_date' => $lockedRequest->reservation_date->format('Y-m-d'),
                'start_time' => $lockedRequest->start_time->format('H:i'),
                'end_time' => $lockedRequest->end_time->format('H:i'),
            ];

            $this->assertRoomCanAccept($room, $lockedRequest->attendees);
            $this->assertRoomHasNoConflict(
                $room->id,
                $data,
                $lockedRequest->id,
                [ReservationRequest::STATUS_APPROVED],
            );

            $studentName = trim("{$lockedRequest->student?->first_name} {$lockedRequest->student?->last_name}")
                ?: ($lockedRequest->student?->username ?? 'Student');
            $schedule = Schedule::create([
                'room_id' => $room->id,
                'event_title' => $lockedRequest->purpose,
                'event_type' => 'other',
                'date' => $data['reservation_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'day_of_week' => Carbon::parse($data['reservation_date'])->englishDayOfWeek,
                'number_of_participants' => $lockedRequest->attendees,
                'requester_id' => $lockedRequest->student_id,
                'requester_name' => $studentName,
                'description' => $lockedRequest->remarks,
                'agenda' => "Approved student reservation request #{$lockedRequest->id}",
                'status' => 'approved',
                'is_recurring' => false,
            ]);

            $lockedRequest->update([
                'status' => ReservationRequest::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'approved_at' => now(),
                'rejected_at' => null,
                'admin_response' => null,
                'schedule_id' => $schedule->id,
            ]);

            $lockedRequest->load(['student', 'room.building', 'room.college', 'reviewer', 'schedule']);
            $this->notifications->notifyStudentInSystem($lockedRequest, 'approved');

            return $lockedRequest;
        }, 3);

        $this->notifications->queueStudentEmail($request, 'approved');

        return $request;
    }

    public function reject(
        ReservationRequest $reservationRequest,
        UserAccount $admin,
        string $message,
    ): ReservationRequest {
        $request = DB::transaction(function () use ($reservationRequest, $admin, $message) {
            $lockedRequest = ReservationRequest::query()
                ->lockForUpdate()
                ->findOrFail($reservationRequest->id);

            if ($lockedRequest->status !== ReservationRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Only pending reservation requests can be rejected.',
                ]);
            }

            $lockedRequest->update([
                'status' => ReservationRequest::STATUS_REJECTED,
                'reviewed_by' => $admin->id,
                'approved_at' => null,
                'rejected_at' => now(),
                'admin_response' => trim($message),
            ]);

            $lockedRequest->load(['student', 'room.building', 'room.college', 'reviewer']);
            $this->notifications->notifyStudentInSystem($lockedRequest, 'rejected');

            return $lockedRequest;
        }, 3);

        $this->notifications->queueStudentEmail($request, 'rejected');

        return $request;
    }

    private function assertWithinOperatingHours(string $startTime, string $endTime): void
    {
        if ($startTime < RoomAvailabilityService::OPENING_TIME || $endTime > RoomAvailabilityService::CLOSING_TIME) {
            throw ValidationException::withMessages([
                'start_time' => 'Reservations must be within 7:00 AM and 9:00 PM.',
            ]);
        }
    }

    private function assertRoomCanAccept(Room $room, int $attendees): void
    {
        if (strtolower((string) $room->status) !== 'available') {
            throw ValidationException::withMessages([
                'room_id' => 'The selected room is not currently available.',
            ]);
        }

        if ($room->capacity && $attendees > $room->capacity) {
            throw ValidationException::withMessages([
                'attendees' => "The selected room can accommodate at most {$room->capacity} attendees.",
            ]);
        }
    }

    private function assertStudentHasNoOverlap(int $studentId, array $data): void
    {
        $overlap = ReservationRequest::query()
            ->where('student_id', $studentId)
            ->whereDate('reservation_date', $data['reservation_date'])
            ->whereIn('status', self::BLOCKING_REQUEST_STATUSES)
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_time' => 'You already have an overlapping pending or approved reservation request.',
            ]);
        }
    }

    private function assertRoomHasNoConflict(
        int $roomId,
        array $data,
        ?int $ignoreRequestId = null,
        array $requestStatuses = self::BLOCKING_REQUEST_STATUSES,
    ): void {
        $schedule = $this->scheduleConflictQuery($roomId, $data)->first();
        if ($schedule) {
            throw ValidationException::withMessages([
                'room_id' => sprintf(
                    'The room is no longer available from %s to %s because it conflicts with an approved schedule.',
                    Carbon::parse($schedule->start_time)->format('g:i A'),
                    Carbon::parse($schedule->end_time)->format('g:i A'),
                ),
            ]);
        }

        $requestQuery = $this->reservationConflictQuery($roomId, $data, $requestStatuses);
        if ($ignoreRequestId) {
            $requestQuery->whereKeyNot($ignoreRequestId);
        }

        if ($requestQuery->exists()) {
            throw ValidationException::withMessages([
                'room_id' => 'The room is no longer available because another pending or approved request overlaps this time.',
            ]);
        }
    }

    private function roomConflicts(int $roomId, string $date, string $startTime, string $endTime): array
    {
        $data = [
            'reservation_date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];

        $schedules = $this->scheduleConflictQuery($roomId, $data)
            ->get(['id', 'start_time', 'end_time', 'status'])
            ->map(fn (Schedule $schedule) => [
                'source' => 'schedule',
                'status' => $schedule->status,
                'start_time' => $schedule->start_time->format('H:i'),
                'end_time' => $schedule->end_time->format('H:i'),
                'label' => 'Approved schedule',
            ])
            ->toBase();

        $requests = $this->reservationConflictQuery($roomId, $data, self::BLOCKING_REQUEST_STATUSES)
            ->get(['id', 'start_time', 'end_time', 'status'])
            ->map(fn (ReservationRequest $request) => [
                'source' => 'reservation_request',
                'status' => $request->status,
                'start_time' => $request->start_time->format('H:i'),
                'end_time' => $request->end_time->format('H:i'),
                'label' => $request->status === ReservationRequest::STATUS_PENDING
                    ? 'Pending reservation request'
                    : 'Approved reservation',
            ])
            ->toBase();

        return $schedules->merge($requests)
            ->sortBy('start_time')
            ->values()
            ->all();
    }

    private function scheduleConflictQuery(int $roomId, array $data): Builder
    {
        return Schedule::query()
            ->where('room_id', $roomId)
            ->whereDate('date', $data['reservation_date'])
            ->whereIn('status', self::BLOCKING_SCHEDULE_STATUSES)
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time']);
    }

    private function reservationConflictQuery(int $roomId, array $data, array $statuses): Builder
    {
        return ReservationRequest::query()
            ->where('room_id', $roomId)
            ->whereDate('reservation_date', $data['reservation_date'])
            ->whereIn('status', $statuses)
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time']);
    }
}
