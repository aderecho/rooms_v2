<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationRequestResource;
use App\Models\ReservationRequest;
use App\Services\ReservationRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentReservationController extends Controller
{
    public function __construct(
        private readonly ReservationRequestService $service,
    ) {}

    public function index(Request $request): Response
    {
        $student = $request->user();
        abort_unless($student?->user_type === 'student', 403);

        $status = strtolower((string) $request->query('status', 'all'));
        $query = ReservationRequest::query()
            ->with(['student', 'room.building', 'room.college', 'reviewer'])
            ->where('student_id', $student->id)
            ->latest();

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $requests = $query->paginate(10)->withQueryString();

        return Inertia::render('MyReservations', [
            'requests' => ReservationRequestResource::collection($requests),
            'totals' => $this->totals($student->id),
            'filters' => ['status' => $status],
            'operatingHours' => [
                'opening' => \App\Services\RoomAvailabilityService::OPENING_TIME,
                'closing' => \App\Services\RoomAvailabilityService::CLOSING_TIME,
            ],
        ]);
    }

    public function availability(Request $request)
    {
        Gate::authorize('create', ReservationRequest::class);

        $validated = $request->validate([
            'reservation_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'attendees' => ['nullable', 'integer', 'min:1'],
        ]);

        $rooms = $this->service->availability(
            $validated['reservation_date'],
            $validated['start_time'],
            $validated['end_time'],
        );

        if (! empty($validated['attendees'])) {
            $rooms = collect($rooms)->map(function (array $room) use ($validated) {
                $capacityExceeded = $room['capacity'] && $validated['attendees'] > $room['capacity'];
                if ($capacityExceeded) {
                    $room['is_available'] = false;
                    $room['unavailable_reason'] = "Capacity is limited to {$room['capacity']} attendees.";
                }

                return $room;
            })->values()->all();
        }

        return response()->json([
            'rooms' => $rooms,
            'available_count' => collect($rooms)->where('is_available', true)->count(),
        ]);
    }

    public function store(StoreReservationRequest $request)
    {
        $reservation = $this->service->submit($request->user(), $request->validated());

        return redirect()
            ->route('student.reservations.show', $reservation)
            ->with('success', 'Your reservation request was submitted and is pending administrator review.');
    }

    public function show(Request $request, ReservationRequest $reservationRequest): Response
    {
        $this->authorize('view', $reservationRequest);
        abort_unless($request->user()?->user_type === 'student', 403);

        $reservationRequest->load(['student', 'room.building', 'room.college', 'reviewer', 'schedule']);

        return Inertia::render('ReservationRequestDetail', [
            'reservationRequest' => new ReservationRequestResource($reservationRequest),
            'viewerMode' => 'student',
        ]);
    }

    private function totals(int $studentId): array
    {
        $counts = ReservationRequest::query()
            ->where('student_id', $studentId)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'all' => (int) $counts->sum(),
            'pending' => (int) ($counts['pending'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
        ];
    }
}
