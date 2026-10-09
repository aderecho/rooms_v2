<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReservationStatusHistory extends Model
{
    protected $guarded = ['id'];

    public function actor()
    {
        return $this->belongsTo(UserAccount::class, 'actor_id')->withTrashed();
    }
}
