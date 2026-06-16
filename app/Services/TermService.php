<?php

namespace App\Services;

use App\Models\Term;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TermService
{
    public function getAllTerms($search = null)
    {
        $query = Term::orderBy('academic_year', 'desc')
            ->orderBy('start_date', 'desc')
            ->withCount('schedules');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('term_name', 'like', "%{$search}%")
                  ->orWhere('term_code', 'like', "%{$search}%")
                  ->orWhere('academic_year', 'like', "%{$search}%");
            });
        }

        return $query->paginate(10);
    }

    public function getCurrentTerm()
    {
        return Term::where('is_current', true)->first();
    }

    public function getTermStats($termId)
    {
        $term = Term::findOrFail($termId);

        return [
            'total_schedules' => $term->schedules()->count(),
            'by_status' => $term->schedules()
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray(),
            'by_event_type' => $term->schedules()
                ->select('event_type', DB::raw('count(*) as count'))
                ->groupBy('event_type')
                ->get()
                ->pluck('count', 'event_type')
                ->toArray(),
            'upcoming_schedules' => $term->schedules()
                ->where('date', '>=', now()->format('Y-m-d'))
                ->where('status', 'approved')
                ->count(),
            'completed_schedules' => $term->schedules()
                ->where('status', 'completed')
                ->count(),
            'days_remaining' => max(0, Carbon::parse($term->end_date)->diffInDays(now())),
            'progress_percentage' => $this->calculateTermProgress($term),
        ];
    }

    private function calculateTermProgress($term)
    {
        $totalDays = Carbon::parse($term->start_date)->diffInDays($term->end_date);
        $daysPassed = Carbon::parse($term->start_date)->diffInDays(now());

        if ($totalDays <= 0) return 100;

        $progress = min(100, max(0, ($daysPassed / $totalDays) * 100));
        return round($progress, 2);
    }

    public function setCurrentTerm($termId)
    {
        // Unset any other current term
        Term::where('is_current', true)->update(['is_current' => false]);

        // Set the new current term
        $term = Term::findOrFail($termId);
        $term->update(['is_current' => true]);

        return $term;
    }

    public function createTerm($data)
    {
        // If this term is set as current, unset any other current term
        if (isset($data['is_current']) && $data['is_current']) {
            Term::where('is_current', true)->update(['is_current' => false]);
        }

        return Term::create($data);
    }

    public function updateTerm($termId, $data)
    {
        $term = Term::findOrFail($termId);

        // If this term is set as current, unset any other current term
        if (isset($data['is_current']) && $data['is_current']) {
            Term::where('id', '!=', $termId)
                ->where('is_current', true)
                ->update(['is_current' => false]);
        }

        $term->update($data);
        return $term;
    }

    public function deleteTerm($termId)
    {
        $term = Term::findOrFail($termId);

        // Check if term has schedules
        if ($term->schedules()->count() > 0) {
            throw new \Exception('Cannot delete term because it has associated schedules.');
        }

        $term->delete();
        return true;
    }

    public function getAcademicCalendar($termId = null)
    {
        $term = $termId ? Term::findOrFail($termId) : $this->getCurrentTerm();

        if (!$term) {
            return [];
        }

        $calendar = [
            'term' => $term,
            'events' => [
                [
                    'title' => 'Term Start',
                    'date' => $term->start_date,
                    'type' => 'important'
                ],
                [
                    'title' => 'Enrollment Start',
                    'date' => $term->enrollment_start,
                    'type' => 'enrollment'
                ],
                [
                    'title' => 'Enrollment End',
                    'date' => $term->enrollment_end,
                    'type' => 'enrollment'
                ],
                [
                    'title' => 'Classes Start',
                    'date' => $term->classes_start,
                    'type' => 'important'
                ],
                [
                    'title' => 'Classes End',
                    'date' => $term->classes_end,
                    'type' => 'important'
                ],
                [
                    'title' => 'Examination Start',
                    'date' => $term->examination_start,
                    'type' => 'exam'
                ],
                [
                    'title' => 'Examination End',
                    'date' => $term->examination_end,
                    'type' => 'exam'
                ],
                [
                    'title' => 'Term End',
                    'date' => $term->end_date,
                    'type' => 'important'
                ],
            ]
        ];

        // Sort events by date
        usort($calendar['events'], function ($a, $b) {
            return strtotime($a['date']) <=> strtotime($b['date']);
        });

        return $calendar;
    }
}
