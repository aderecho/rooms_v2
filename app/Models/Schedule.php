<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'event_title',
        'event_type',
        'course_code',
        'course_name',
        'section',
        'faculty_name',
        'faculty_id',
        'date',
        'start_time',
        'end_time',
        'day_of_week',
        'number_of_participants',
        'requester_id',
        'requester_name',
        'description',
        'agenda',
        'organizer',
        'equipment_needed',
        'additional_requirements',
        'status',
        'is_recurring',
        'recurrence_pattern',
        'term_id',
        'cfic_id',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'equipment_needed' => 'array',
        'additional_requirements' => 'array',
        'recurrence_pattern' => 'array',
        'is_recurring' => 'boolean',
    ];

    // Relationships
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function faculty()
    {
        return $this->belongsTo(UserAccount::class, 'faculty_id');
    }

    public function requester()
    {
        return $this->belongsTo(UserAccount::class, 'requester_id');
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    // Accessor for event duration
    public function getDurationAttribute()
    {
        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);
        return $start->diff($end)->format('%H:%I');
    }

    // Accessor for day (compatibility with frontend)
    public function getDayAttribute()
    {
        return $this->day_of_week;
    }

    // Scope for approved schedules
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // Scope for today's schedules
    public function scopeToday($query)
    {
        return $query->where('date', today());
    }

    // Scope for upcoming schedules
    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', today())->where('status', 'approved');
    }
}
