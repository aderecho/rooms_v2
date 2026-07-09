<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Schedule;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $schedules = [

            [
                'room_id' => 11,
                'event_title' => 'IT101 Programming Fundamentals',
                'event_type' => 'class',
                'course_code' => 'IT101',
                'course_name' => 'Programming Fundamentals',
                'section' => 'BSIT-1A',
                'faculty_name' => 'Danyka Hermiston',
                'faculty_id' => 2,
                'date' => '2026-08-03',
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'day_of_week' => 'Monday',
                'number_of_participants' => 40,
                'requester_id' => 1,
                'requester_name' => 'System Administrator',
                'description' => 'Regular class',
                'agenda' => null,
                'organizer' => 'CSS',
                'equipment_needed' => json_encode([
                    'Projector',
                    'Whiteboard'
                ]),
                'additional_requirements' => json_encode([]),
                'status' => 'approved',
                'is_recurring' => true,
                'recurrence_pattern' => json_encode([
                    'frequency' => 'weekly'
                ]),
                'term_id' => 1,
                'cfic_id' => 'CFIC-001'
            ],

        ];

        foreach ($schedules as $schedule) {
            Schedule::create($schedule);
        }
    }
}
