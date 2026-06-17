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
        /**
         * return all rooms [room_name, room_code, capacity]
         */
        
        return response()->json([
            'rooms' => $rooms,
        ]);
    }
}
