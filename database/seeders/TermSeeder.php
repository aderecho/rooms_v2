<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Term;
use Carbon\Carbon;

class TermSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing terms
        Term::truncate();

        $terms = [
            [
                'term_name' => 'First Semester 2024',
                'term_code' => '2024-1',
                'term_type' => 'semester',
                'start_date' => '2024-08-01',
                'end_date' => '2024-12-15',
                'enrollment_start' => '2024-07-15',
                'enrollment_end' => '2024-07-31',
                'classes_start' => '2024-08-05',
                'classes_end' => '2024-12-10',
                'examination_start' => '2024-12-11',
                'examination_end' => '2024-12-15',
                'is_current' => false,
                'status' => 'completed',
                'academic_year' => '2024',
                'notes' => 'Regular academic semester',
            ],
            [
                'term_name' => 'Second Semester 2024',
                'term_code' => '2024-2',
                'term_type' => 'semester',
                'start_date' => '2025-01-06',
                'end_date' => '2025-05-30',
                'enrollment_start' => '2025-01-02',
                'enrollment_end' => '2025-01-05',
                'classes_start' => '2025-01-06',
                'classes_end' => '2025-05-25',
                'examination_start' => '2025-05-26',
                'examination_end' => '2025-05-30',
                'is_current' => true,
                'status' => 'active',
                'academic_year' => '2024',
                'notes' => 'Current semester',
            ],
            [
                'term_name' => 'Summer 2024',
                'term_code' => '2024-S',
                'term_type' => 'summer',
                'start_date' => '2024-06-01',
                'end_date' => '2024-07-31',
                'enrollment_start' => '2024-05-15',
                'enrollment_end' => '2024-05-31',
                'classes_start' => '2024-06-01',
                'classes_end' => '2024-07-26',
                'examination_start' => '2024-07-27',
                'examination_end' => '2024-07-31',
                'is_current' => false,
                'status' => 'completed',
                'academic_year' => '2024',
                'notes' => 'Summer term',
            ],
            [
                'term_name' => 'First Semester 2025',
                'term_code' => '2025-1',
                'term_type' => 'semester',
                'start_date' => '2025-08-01',
                'end_date' => '2025-12-15',
                'enrollment_start' => '2025-07-15',
                'enrollment_end' => '2025-07-31',
                'classes_start' => '2025-08-05',
                'classes_end' => '2025-12-10',
                'examination_start' => '2025-12-11',
                'examination_end' => '2025-12-15',
                'is_current' => false,
                'status' => 'upcoming',
                'academic_year' => '2025',
                'notes' => 'Upcoming semester',
            ],
            [
                'term_name' => 'Mid-Year Term 2024',
                'term_code' => '2024-M',
                'term_type' => 'special',
                'start_date' => '2024-05-15',
                'end_date' => '2024-06-30',
                'enrollment_start' => '2024-05-01',
                'enrollment_end' => '2024-05-14',
                'classes_start' => '2024-05-15',
                'classes_end' => '2024-06-25',
                'examination_start' => '2024-06-26',
                'examination_end' => '2024-06-30',
                'is_current' => false,
                'status' => 'completed',
                'academic_year' => '2024',
                'notes' => 'Special mid-year term',
            ],
        ];

        foreach ($terms as $term) {
            Term::create($term);
        }

        $this->command->info('✅ Terms seeded successfully!');
    }
}
