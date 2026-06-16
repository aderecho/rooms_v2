<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    use HasFactory;

    protected $fillable = [
        'building_name',
        'address',
        'description',
        'total_floors',
        'total_rooms',
        'has_elevator',
        'has_parking',
        'restroom_count',
        'ramp_count',
        'college_id',
    ];

    protected $casts = [
        'has_elevator' => 'boolean',
        'has_parking' => 'boolean',
    ];

    // Relationships
    public function college()
    {
        return $this->belongsTo(College::class, 'college_id');
    }

    public function rooms()
    {
        return $this->hasMany(Room::class, 'building_id');
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class, 'building_id');
    }
}
