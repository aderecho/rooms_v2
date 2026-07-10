<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleImportLog extends Model
{
    //
    protected $fillable = [
        'user_id',
        'source',
        'total_records',
        'inserted_records',
        'failed_records',
        'errors',
    ];


    protected $casts = [
        'errors' => 'array',
    ];
}
