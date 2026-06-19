<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        $building = $this->building;

        return [
            'room_name' => $this->room_name,
            'room_code' => $this->room_code,
            'capacity' => $this->capacity,
            'building' => $building?->building_name,
            'building_name' => $building?->building_name,
            'description' => $building?->description,
        ];
    }
}
