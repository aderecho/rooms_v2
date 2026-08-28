<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'student_id',
        'room_id',
        'reservation_date',
        'start_time',
        'end_time',
        'purpose',
        'attendees',
        'remarks',
        'status',
        'reviewed_by',
        'approved_at',
        'rejected_at',
        'admin_response',
        'schedule_id',
    ];

    protected $casts = [
        'reservation_date' => 'date:Y-m-d',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'attendees' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(UserAccount::class, 'student_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(UserAccount::class, 'reviewed_by');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
