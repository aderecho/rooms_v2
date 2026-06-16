<?php

namespace App\Services;

use App\Models\Room;

class RoomService
{
   public function getRooms(int $perPage = 10, ?string $search = null)
    {
        return Room::with('building', 'college', 'department', 'roomType', 'assignedUser')
            ->orderByDesc('created_at')
            ->when($search, fn($query) => $query->where('room_name', 'like', "%{$search}%"))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function getRoomsForApi()
    {
        return Room::with('roomType', 'building')
            ->select('id', 'room_name', 'room_code','room_type_id', 'building_id') // Select only necessary columns
            ->orderBy('room_name')
            ->get();
    }
    public function getRoomById(int $id)
    {
        return Room::with(['building', 'roomType'])->findOrFail($id);
    }
}
