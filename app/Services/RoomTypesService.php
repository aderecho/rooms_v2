<?php

namespace App\Services;

use App\Models\RoomType;

class RoomTypesService
{
   public function getRoomTypes(int $perPage = 10, ?string $search = null)
    {
        return RoomType::with('rooms')
            ->orderByDesc('created_at')
            ->when($search, fn($query) => $query->where('room_type_name', 'like', "%{$search}%"))
            ->paginate($perPage)
            ->withQueryString();
    }
}
