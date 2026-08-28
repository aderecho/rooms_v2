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
            'status' => ['nullable', 'string', 'in:all,pending,approved,rejected'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $status = $filters['status'] ?? 'pending';
        $search = trim((string) ($filters['search'] ?? ''));

        $query = ReservationRequest::query()
            ->with(['student', 'room.building', 'room.college', 'reviewer'])
            ->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if (! empty($filters['date'])) {
            $query->whereDate('reservation_date', $filters['date']);
        }
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('purpose', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($studentQuery) use ($search) {
                        $studentQuery
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('room', function ($roomQuery) use ($search) {
                        $roomQuery
                            ->where('room_name', 'like', "%{$search}%")
                            ->orWhere('room_code', 'like', "%{$search}%");
                    });
            });
        }

        return Inertia::render('ReservationRequests', [
            'requests' => ReservationRequestResource::collection($query->paginate(15)->withQueryString()),
            'totals' => $this->totals(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date' => $filters['date'] ?? '',
            ],
        ]);
    }

    public function show(ReservationRequest $reservationRequest): Response
    {
        $this->authorize('view', $reservationRequest);
        abort_unless(request()->user()?->user_type === 'admin', 403);

        $reservationRequest->load(['student', 'room.building', 'room.college', 'reviewer', 'schedule']);

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

        return back()->with('success', 'Reservation request rejected and the student was notified.');
    }

    private function totals(): array
    {
        $counts = ReservationRequest::query()
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
