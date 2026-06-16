<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Term extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'term_name',
        'term_code',
        'term_type',
        'start_date',
        'end_date',
        'enrollment_start',
        'enrollment_end',
        'classes_start',
        'classes_end',
        'examination_start',
        'examination_end',
        'is_current',
        'status',
        'academic_year',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'enrollment_start' => 'date',
        'enrollment_end' => 'date',
        'classes_start' => 'date',
        'classes_end' => 'date',
        'examination_start' => 'date',
        'examination_end' => 'date',
        'is_current' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    // Add this to get schedules count
    public function getSchedulesCountAttribute()
    {
        return $this->schedules()->count();
    }

    // Scope for current term
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    // Scope for active terms
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Scope for upcoming terms
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'upcoming');
    }

    // Scope for completed terms
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Accessor for formatted date range
    public function getDateRangeAttribute()
    {
        return $this->start_date->format('M d, Y') . ' - ' . $this->end_date->format('M d, Y');
    }

    // Accessor for academic year display
    public function getAcademicYearDisplayAttribute()
    {
        return 'AY ' . $this->academic_year;
    }

    // Method to check if term is deletable
    public function isDeletable()
    {
        return $this->schedules()->count() === 0;
    }
}
