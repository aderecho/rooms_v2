<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
        'Room'  => $this->room_name,
        'Facility' => $this->room_code,
        'Type' => $this->roomType->room_type_name,
        'Building' => $this->building?->building_name,
    ];

    }
}
