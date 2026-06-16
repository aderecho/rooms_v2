<?php

namespace App\Http\Controllers\API;

use App\Models\Room;
use App\Models\College;
use Illuminate\Http\Request;

class RoomController
{
    public function index()
    {
        $rooms = Room::all();
        
        return response()->json([
            'rooms' => $rooms,
        ]);
    }
}
