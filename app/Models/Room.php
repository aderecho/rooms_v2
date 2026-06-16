<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_name',
        'room_code',
        'building_id',
        'college_id',
        'department_id',
        'room_type_id',
        'assigned_user_id',
        'floor_number',
        'location',
        'capacity',
        'area_sqm',
        'facilities',
        'status',
        'notes',
        'description',
        'equipments'
    ];

    protected $casts = [
        'facilities' => 'array',
        'equipments' => 'array' // Add this cast
    ];

    // Relationships
    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(UserAccount::class, 'assigned_user_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    // New method to get room utilization statistics
    public function getUtilizationStats()
    {
        $today = now()->format('Y-m-d');

        return [
            'today_schedules' => $this->schedules()->where('date', $today)->count(),
            'total_schedules' => $this->schedules()->count(),
            'upcoming_schedules' => $this->schedules()->where('date', '>=', $today)->count(),
        ];
    }

    // Accessor for full room name
    public function getFullRoomAttribute()
    {
        $building = $this->building ? $this->building->building_name : 'No Building';
        return "{$building} - {$this->room_name} ({$this->room_code})";
    }

    // Scope for available rooms
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    // Scope for occupied rooms
    public function scopeOccupied($query)
    {
        return $query->where('status', 'occupied');
    }
}
