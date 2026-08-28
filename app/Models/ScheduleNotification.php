<?php

namespace App\Models;

use App\Events\ScheduleNotificationCreated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleNotification extends Model
{
    protected static function booted(): void
    {
        static::created(function (ScheduleNotification $notification): void {
            broadcast(new ScheduleNotificationCreated($notification));
        });
    }

    protected $fillable = [
        'user_id',
        'schedule_id',
        'reservation_request_id',
        'type',
        'title',
        'message',
        'action_url',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserAccount::class, 'user_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function reservationRequest(): BelongsTo
    {
        return $this->belongsTo(ReservationRequest::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
