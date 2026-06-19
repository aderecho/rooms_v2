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
        $buildingName = $this->building?->building_name;

        return [
            'room_name' => $this->room_name,
            'room_code' => $this->room_code,
            'capacity' => $this->capacity,
            'building' => $buildingName,
            'building_name' => $buildingName,
        ];
    }
}
