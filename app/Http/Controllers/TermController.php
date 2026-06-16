<?php

namespace App\Http\Controllers;

use App\Models\Term;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TermController extends Controller
{
    public function index(Request $request)
    {
        $terms = Term::orderBy('start_date', 'desc')
            ->paginate(20);

        $currentTerm = Term::where('is_current', true)->first();

        return Inertia::render('Terms', [
            'terms' => $terms,
            'current_term' => $currentTerm,
            'stats' => [
                'total' => Term::count(),
                'active' => Term::where('status', 'active')->count(),
                'completed' => Term::where('status', 'completed')->count(),
                'upcoming' => Term::where('status', 'upcoming')->count(),
            ]
        ]);
    }

    public function getAll(Request $request)
    {
        $query = Term::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('term_name', 'like', "%{$search}%")
                    ->orWhere('term_code', 'like', "%{$search}%")
                    ->orWhere('academic_year', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('term_type')) {
            $query->where('term_type', $request->term_type);
        }

        $sortField = $request->get('sort_field', 'start_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        return response()->json($query->paginate($request->get('per_page', 20)));
    }

    public function getCurrent()
    {
        $currentTerm = Term::where('is_current', true)
            ->withCount('schedules')
            ->first();

        if (!$currentTerm) {
            return response()->json([
                'message' => 'No current term set'
            ], 404);
        }

        return response()->json($currentTerm);
    }

    public function getStats(Term $term)
    {
        $schedulesCount = $term->schedules()->count();
        $approvedSchedules = $term->schedules()->where('status', 'approved')->count();
        $pendingSchedules = $term->schedules()->where('status', 'pending')->count();

        $schedulesByType = $term->schedules()
            ->select('event_type', DB::raw('COUNT(*) as count'))
            ->groupBy('event_type')
            ->get()
            ->pluck('count', 'event_type')
            ->toArray();

        $schedulesByDay = $term->schedules()
            ->select('day_of_week', DB::raw('COUNT(*) as count'))
            ->groupBy('day_of_week')
            ->get()
            ->pluck('count', 'day_of_week')
            ->toArray();

        $topRooms = $term->schedules()
            ->join('rooms', 'schedules.room_id', '=', 'rooms.id')
            ->select('rooms.room_name', 'rooms.room_code', DB::raw('COUNT(*) as booking_count'))
            ->groupBy('rooms.id', 'rooms.room_name', 'rooms.room_code')
            ->orderBy('booking_count', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'term' => $term,
            'stats' => [
                'total_schedules' => $schedulesCount,
                'approved_schedules' => $approvedSchedules,
                'pending_schedules' => $pendingSchedules,
                'approval_rate' => $schedulesCount > 0 ? round(($approvedSchedules / $schedulesCount) * 100, 2) : 0,
                'schedules_by_type' => $schedulesByType,
                'schedules_by_day' => $schedulesByDay,
                'top_rooms' => $topRooms,
            ],
            'upcoming_schedules' => $term->schedules()
                ->with('room')
                ->where('date', '>=', now())
                ->where('status', 'approved')
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(10)
                ->get(),
        ]);
    }

    public function setCurrent($id)
    {
        // First, unset all current terms
        Term::where('is_current', true)->update(['is_current' => false]);

        // Set the new current term
        $term = Term::findOrFail($id);
        $term->update(['is_current' => true]);

        return response()->json([
            'message' => 'Current term updated successfully',
            'current_term' => $term,
        ]);
    }

    public function getCalendar($id)
    {
        $term = Term::findOrFail($id);

        $schedules = $term->schedules()
            ->with(['room', 'faculty'])
            ->where('status', 'approved')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Group schedules by month for calendar view
        $calendar = [];
        foreach ($schedules as $schedule) {
            $monthYear = \Carbon\Carbon::parse($schedule->date)->format('Y-m');

            if (!isset($calendar[$monthYear])) {
                $calendar[$monthYear] = [
                    'month' => \Carbon\Carbon::parse($schedule->date)->format('F Y'),
                    'schedules' => [],
                ];
            }

            $calendar[$monthYear]['schedules'][] = $schedule;
        }

        return response()->json([
            'term' => $term,
            'calendar' => $calendar,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'term_name' => 'required|string|max:255|unique:terms,term_name',
            'term_code' => 'required|string|max:50|unique:terms,term_code',
            'term_type' => 'required|in:semester,trimester,quarter,summer,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'enrollment_start' => 'nullable|date|before:classes_start',
            'enrollment_end' => 'nullable|date|after:enrollment_start|before:classes_end',
            'classes_start' => 'required|date|after_or_equal:start_date',
            'classes_end' => 'required|date|before_or_equal:end_date',
            'examination_start' => 'nullable|date|after:classes_end',
            'examination_end' => 'nullable|date|after:examination_start|before_or_equal:end_date',
            'is_current' => 'boolean',
            'status' => 'required|in:upcoming,active,completed,cancelled',
            'academic_year' => 'required|string|max:20',
            'notes' => 'nullable|string',
        ]);

        // If setting as current, unset other current terms
        if ($validated['is_current'] ?? false) {
            Term::where('is_current', true)->update(['is_current' => false]);
        }

        $term = Term::create($validated);

        return response()->json([
            'message' => 'Term created successfully',
            'term' => $term,
        ], 201);
    }

    public function update(Request $request, Term $term)
    {
        $validated = $request->validate([
            'term_name' => 'sometimes|required|string|max:255|unique:terms,term_name,' . $term->id,
            'term_code' => 'sometimes|required|string|max:50|unique:terms,term_code,' . $term->id,
            'term_type' => 'sometimes|required|in:semester,trimester,quarter,summer,other',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'enrollment_start' => 'nullable|date|before:classes_start',
            'enrollment_end' => 'nullable|date|after:enrollment_start|before:classes_end',
            'classes_start' => 'sometimes|required|date|after_or_equal:start_date',
            'classes_end' => 'sometimes|required|date|before_or_equal:end_date',
            'examination_start' => 'nullable|date|after:classes_end',
            'examination_end' => 'nullable|date|after:examination_start|before_or_equal:end_date',
            'is_current' => 'boolean',
            'status' => 'sometimes|required|in:upcoming,active,completed,cancelled',
            'academic_year' => 'sometimes|required|string|max:20',
            'notes' => 'nullable|string',
        ]);

        // If setting as current, unset other current terms
        if (isset($validated['is_current']) && $validated['is_current']) {
            Term::where('is_current', true)->where('id', '!=', $term->id)->update(['is_current' => false]);
        }

        $term->update($validated);

        return response()->json([
            'message' => 'Term updated successfully',
            'term' => $term,
        ]);
    }

    public function destroy(Term $term)
    {
        // Check if term has schedules
        if ($term->schedules()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete term with existing schedules'
            ], 422);
        }

        $term->delete();

        return response()->json([
            'message' => 'Term deleted successfully'
        ]);
    }
}
