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
        $building = $this->building;

        return [
            'id' => $this->id,
            'room_name' => $this->room_name,
            'room_code' => $this->room_code,
            'is_public' => $this->is_public,
            'capacity' => $this->capacity,
            'equipments' => $this->equipments ?? [],
            'building' => $building?->building_name,
            'building_name' => $building?->building_name,
            'description' => $building?->description,
        ];
    }
}
