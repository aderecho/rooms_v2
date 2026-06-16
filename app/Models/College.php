<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class College extends Model
{
    use HasFactory;

    protected $fillable = [
        'college_name',
        'college_code',
        'description',
        'dean_id',
        'contact_email',
        'contact_phone',
    ];

    // Relationships
    public function dean()
    {
        return $this->belongsTo(UserAccount::class, 'dean_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class, 'college_id');
    }

    public function buildings()
    {
        return $this->hasMany(Building::class, 'college_id');
    }

    public function rooms()
    {
        return $this->hasMany(Room::class, 'college_id');
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class, 'college_id');
    }

    public function userAccounts()
    {
        return $this->hasMany(UserAccount::class, 'college_id');
    }
}
