<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Schedule;
use App\Models\Room;
use App\Models\UserAccount;
use App\Models\Term;
use Carbon\Carbon;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        $startTime = $this->faker->time('H:i');
        $endTime = Carbon::parse($startTime)->addHours(2)->format('H:i');
        $date = $this->faker->dateTimeBetween('now', '+30 days')->format('Y-m-d');
        $dayOfWeek = strtolower(Carbon::parse($date)->englishDayOfWeek);

        // Get real data if available
        $room = Room::inRandomOrder()->first();
        $faculty = UserAccount::where('user_type', 'faculty')->inRandomOrder()->first();
        $requester = UserAccount::whereIn('user_type', ['faculty', 'staff'])->inRandomOrder()->first();
        $term = Term::inRandomOrder()->first();

        return [
            'room_id' => $room?->id ?? Room::factory(),
            'event_title' => $this->faker->sentence(3),
            'event_type' => $this->faker->randomElement(['class', 'meeting', 'event', 'other']),
            'course_code' => strtoupper($this->faker->bothify('??###')),
            'course_name' => $this->faker->words(3, true),
            'section' => $this->faker->randomElement(['A', 'B', 'C', 'D']),
            'faculty_name' => $faculty?->first_name . ' ' . $faculty?->last_name ?? $this->faker->name,
            'faculty_id' => $faculty?->id,
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'day_of_week' => $dayOfWeek,
            'number_of_participants' => $this->faker->numberBetween(10, 100),
            'requester_id' => $requester?->id,
            'requester_name' => $requester?->first_name . ' ' . $requester?->last_name ?? $this->faker->name,
            'description' => $this->faker->paragraph,
            'agenda' => $this->faker->sentence(),
            'organizer' => $this->faker->company,
            'equipment_needed' => json_encode(['projector', 'whiteboard', 'chairs']),
            'additional_requirements' => json_encode(['water', 'snacks']),
            'status' => $this->faker->randomElement(['approved', 'pending', 'cancelled']),
            'is_recurring' => $this->faker->boolean(30),
            'recurrence_pattern' => $this->faker->boolean(30) ? json_encode(['frequency' => 'weekly', 'days' => [1, 3]]) : null,
            'term_id' => $term?->id,
            'cfic_id' => 'CFIC-' . strtoupper($this->faker->bothify('??###')),
        ];
    }
}
