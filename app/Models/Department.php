<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_name',
        'department_code',
        'college_id',
        'department_head_id',
        'description',
        'office_location',
        'contact_email',
        'contact_phone',
    ];

    // Relationships
    public function college()
    {
        return $this->belongsTo(College::class);
    }

    public function head()
    {
        return $this->belongsTo(UserAccount::class, 'department_head_id');
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class);
    }

    public function userAccounts()
    {
        return $this->hasMany(UserAccount::class);
    }

    // Accessor for full department name with college
    public function getFullDepartmentAttribute()
    {
        return $this->college ? "{$this->college->college_name} - {$this->department_name}" : $this->department_name;
    }

    // Accessor for head full name
    public function getHeadFullNameAttribute()
    {
        return $this->head ? $this->head->full_name : 'Not Assigned';
    }
}
