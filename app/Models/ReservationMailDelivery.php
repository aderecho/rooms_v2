<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationMailDelivery extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['queued_at' => 'datetime', 'sent_at' => 'datetime'];

    public function reservationRequest()
    {
        return $this->belongsTo(ReservationRequest::class);
    }

    public function recipient()
    {
        return $this->belongsTo(UserAccount::class, 'recipient_id');
    }
}
