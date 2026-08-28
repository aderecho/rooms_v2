<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $studentName = trim("{$this->student?->first_name} {$this->student?->last_name}")
            ?: ($this->student?->username ?? 'Student');
        $reviewerName = trim("{$this->reviewer?->first_name} {$this->reviewer?->last_name}")
            ?: $this->reviewer?->username;

        return [
            'id' => $this->id,
            'student' => $this->student ? [
                'id' => $this->student->id,
                'name' => $studentName,
                'email' => $this->student->email,
            ] : null,
            'room' => $this->room ? [
                'id' => $this->room->id,
                'room_name' => $this->room->room_name,
                'room_code' => $this->room->room_code,
                'capacity' => $this->room->capacity,
                'location' => $this->room->location,
                'building' => $this->room->building?->building_name,
                'college' => $this->room->college?->college_name,
            ] : null,
            'reservation_date' => $this->reservation_date?->format('Y-m-d'),
            'start_time' => $this->start_time?->format('H:i'),
            'end_time' => $this->end_time?->format('H:i'),
            'purpose' => $this->purpose,
            'attendees' => $this->attendees,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'reviewer' => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'name' => $reviewerName,
            ] : null,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'admin_response' => $this->admin_response,
            'schedule_id' => $this->schedule_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
