<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class UserAccount extends Authenticatable
{
    use HasFactory, SoftDeletes, Notifiable;

    protected $table = 'user_accounts';

    protected $fillable = [
        'username',
        'email',
        'password',
        'first_name',
        'last_name',
        'middle_name',
        'employee_id',
        'profile_picture',
        'gender',
        'birth_date',
        'contact_number',
        'address',
        'college_id',
        'department_id',
        'user_type',
        'roles',
        'account_status',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'roles' => 'json',
        'last_login_at' => 'datetime',
        'birth_date' => 'date',
        'account_status' => 'string',
    ];

    // Relationships
    public function college()
    {
        return $this->belongsTo(College::class, 'college_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function deanCollege()
    {
        return $this->hasOne(College::class, 'dean_id');
    }

    public function headDepartment()
    {
        return $this->hasOne(Department::class, 'department_head_id');
    }

    public function assignedRooms()
    {
        return $this->hasMany(Room::class, 'assigned_user_id');
    }

    public function assignedEquipment()
    {
        return $this->hasMany(Equipment::class, 'assigned_user_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'faculty_id');
    }

    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permission, $this->roles ?? [], true);
    }

    public function requestedSchedules()
    {
        return $this->hasMany(Schedule::class, 'requester_id');
    }
}
