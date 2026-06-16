<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Model
{
    use SoftDeletes;

    protected $table = 'user_accounts';

    protected $fillable = [
        'name',
        'email',
        'user_type',
        'college_id',
        'department_id',
        'status',
    ];

    // Relationship: User belongs to a college
    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    // Relationship: User belongs to a department
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    // Relationship: User is dean of a college
    public function deanCollege(): HasOne
    {
        return $this->hasOne(College::class, 'dean_id');
    }

    // Relationship: User is head of a department
    public function departmentHead(): HasOne
    {
        return $this->hasOne(Department::class, 'department_head_id');
    }

    // Relationship: User has many schedules as faculty
    public function facultySchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'faculty_id');
    }

    // Relationship: User has many schedules as requester
    public function requestedSchedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'requester_id');
    }

    // Relationship: User has many assigned rooms
    public function assignedRooms(): HasMany
    {
        return $this->hasMany(Room::class, 'assigned_user_id');
    }

    // Relationship: User has many assigned equipment
    public function assignedEquipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'assigned_user_id');
    }
}
