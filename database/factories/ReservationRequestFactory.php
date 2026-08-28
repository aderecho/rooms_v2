<?php

namespace Database\Factories;

use App\Models\ReservationRequest;
use App\Models\Room;
use App\Models\UserAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationRequestFactory extends Factory
{
    protected $model = ReservationRequest::class;

    public function definition(): array
    {
        return [
            'student_id' => UserAccount::factory()->state([
                'user_type' => 'student',
                'account_status' => 'active',
            ]),
            'room_id' => Room::factory()->state(['status' => 'available']),
            'reservation_date' => now()->addWeek()->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'purpose' => $this->faker->sentence(5),
            'attendees' => 20,
            'remarks' => $this->faker->optional()->sentence(),
            'status' => ReservationRequest::STATUS_PENDING,
        ];
    }
}
