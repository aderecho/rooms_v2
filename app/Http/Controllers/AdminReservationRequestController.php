<?php

namespace App\Http\Controllers;

use App\Http\Requests\RejectReservationRequest;
use App\Http\Resources\ReservationRequestResource;
use App\Models\ReservationRequest;
use App\Services\ReservationRequestService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminReservationRequestController extends Controller
{
    public function __construct(
        private readonly ReservationRequestService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ReservationRequest::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:all,pending,approved,rejected,in_progress,completed,cancelled'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'submitted_date' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);
        $status = $filters['status'] ?? 'all';
        $search = trim((string) ($filters['search'] ?? ''));

        $query = $this->requestsQuery()
            ->with(['student', 'room.building', 'room.college', 'reviewer'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->orderByDesc('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if (! empty($filters['date'])) {
            $query->whereDate('reservation_date', $filters['date']);
        }
        if (! empty($filters['submitted_date'])) {
            $query->whereDate('created_at', $filters['submitted_date']);
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('purpose', 'like', "%{$search}%")
                    ->orWhere('requester_name', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($studentQuery) use ($search) {
                        $studentQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('employee_id', 'like', "%{$search}%")
                            ->orWhere(function ($nameQuery) use ($search) {
                                foreach (preg_split('/\s+/', $search) as $part) {
                                    $nameQuery->where(function ($partQuery) use ($part) {
                                        $partQuery->where('first_name', 'like', "%{$part}%")
                                            ->orWhere('last_name', 'like', "%{$part}%");
                                    });
                                }
                            })
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('room', function ($roomQuery) use ($search) {
                        $roomQuery
                            ->where('room_name', 'like', "%{$search}%")
                            ->orWhere('room_code', 'like', "%{$search}%")
                            ->orWhereHas('building', fn ($buildingQuery) => $buildingQuery->where('building_name', 'like', "%{$search}%"));
                    });
            });
        }

        return Inertia::render('ReservationRequests', [
            'requests' => ReservationRequestResource::collection($query->paginate((int) ($filters['per_page'] ?? 10))->withQueryString()),
            'totals' => $this->totals(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date' => $filters['date'] ?? '',
                'submitted_date' => $filters['submitted_date'] ?? '',
                'per_page' => (int) ($filters['per_page'] ?? 10),
            ],
        ]);
    }

    public function show(ReservationRequest $reservationRequest): Response
    {
        $this->authorize('view', $reservationRequest);
        abort_unless(request()->user()?->user_type === 'admin', 403);

        $reservationRequest->load(['student', 'room.building', 'room.college', 'reviewer', 'schedule', 'student.college', 'student.department', 'history.actor', 'mailDeliveries']);

        return Inertia::render('ReservationRequestDetail', [
            'reservationRequest' => new ReservationRequestResource($reservationRequest),
            'viewerMode' => 'admin',
        ]);
    }

    public function approve(Request $request, ReservationRequest $reservationRequest)
    {
        $this->authorize('approve', $reservationRequest);
        $this->service->approve($reservationRequest, $request->user());

        return back()->with('success', 'Reservation request approved and added to the room calendar.');
    }

    public function reject(RejectReservationRequest $request, ReservationRequest $reservationRequest)
    {
        $this->authorize('reject', $reservationRequest);
        $this->service->reject(
            $reservationRequest,
            $request->user(),
            $request->validated('admin_response'),
        );

        return back()->with('success', 'Reservation request rejected. The student notification has been recorded.');
    }

    /** Merge both booking workflows before filtering and pagination. */
    private function requestsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $reservations = ReservationRequest::query()->selectRaw(
            "id, student_id, room_id, reservation_date, start_time, end_time, purpose, attendees, remarks, status, reviewed_by, approved_at, rejected_at, admin_response, schedule_id, created_at, updated_at, 'reservation' as source, NULL as requester_name"
        );
        $appointments = \App\Models\Schedule::query()
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('reservation_requests')
                    ->whereColumn('reservation_requests.schedule_id', 'schedules.id');
            })
            ->selectRaw("id, requester_id as student_id, room_id, date as reservation_date, start_time, end_time, event_title as purpose, number_of_participants as attendees, description as remarks, status, NULL as reviewed_by, NULL as approved_at, NULL as rejected_at, NULL as admin_response, id as schedule_id, created_at, updated_at, 'appointment' as source, requester_name");

        return ReservationRequest::query()->fromSub(
            $reservations->unionAll($appointments)->toBase(), 'reservation_requests'
        );
    }

    private function totals(): array
    {
        $counts = $this->requestsQuery()
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
