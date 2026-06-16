<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'equipment_name',
        'inventory_id',
        'property_id',
        'description',
        'quantity',
        'room_id',
        'building_id',
        'college_id',
        'department_id',
        'cfic_id',
        'status',
        'brand',
        'model',
        'serial_number',
        'purchase_date',
        'purchase_price',
        'assigned_user_id',
        'specifications',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'specifications' => 'array',
    ];

    // Relationships
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

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

    public function assignedUser()
    {
        return $this->belongsTo(UserAccount::class, 'assigned_user_id');
    }

    // Accessor for equipment location
    public function getLocationAttribute()
    {
        $location = [];

        if ($this->room) {
            $location[] = "Room: {$this->room->room_name} ({$this->room->room_code})";
        }

        if ($this->building) {
            $location[] = "Building: {$this->building->building_name}";
        }

        if ($this->college) {
            $location[] = "College: {$this->college->college_name}";
        }

        return !empty($location) ? implode(' | ', $location) : 'No specific location';
    }

    // Scope for available equipment
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    // Add this method to get equipment with user relationships
    public function scopeWithAssignedUser($query)
    {
        return $query->with(['assignedUser' => function($q) {
            $q->select('id', 'first_name', 'last_name', 'username', 'email');
        }]);
    }
}
