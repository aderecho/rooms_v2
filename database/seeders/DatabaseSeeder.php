<?php

namespace Database\Seeders;

use App\Models\UserAccount;
use App\Models\College;
use App\Models\Department;
use App\Models\Building;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\Equipment;
use App\Models\Term;
use App\Models\Schedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing data
        $this->command->info('Clearing existing data...');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Clear tables in correct order (reverse of dependencies)
        Schedule::truncate();
        Equipment::truncate();
        Room::truncate();
        RoomType::truncate();
        Building::truncate();
        Term::truncate();
        Department::truncate();
        College::truncate();
        UserAccount::truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Create a default admin user
        $this->command->info('Creating admin user...');
        $admin = UserAccount::create([
            'username' => 'admin',
            'email' => 'admin@upcebu.edu.ph',
            'password' => Hash::make('password123'),
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'user_type' => 'admin',
            'account_status' => 'active',
            'roles' => ['admin', 'super_admin'],
        ]);

        // Create some faculty users
        $this->command->info('Creating faculty users...');
        UserAccount::factory()->count(5)->create([
            'user_type' => 'faculty'
        ]);

        // Create some staff users
        UserAccount::factory()->count(3)->create([
            'user_type' => 'staff'
        ]);

        // Create colleges
        $this->command->info('Creating colleges...');
        $colleges = College::factory()->count(4)->create();

        // Set admin as dean for first college
        $colleges[0]->update(['dean_id' => $admin->id]);

        // Create departments
        $this->command->info('Creating departments...');
        $departments = Department::factory()->count(6)->create();

        // Create buildings
        $this->command->info('Creating buildings...');
        $buildings = Building::factory()->count(5)->create();

        // Create room types
        $this->command->info('Creating room types...');
        $roomTypes = RoomType::factory()->count(4)->create();

        // Create rooms
        $this->command->info('Creating rooms...');
        $rooms = Room::factory()->count(10)->create();

        // Demo rooms with named equipment quantities (UG 114, AVR 201, etc.)
        $this->command->info('Creating demo rooms with equipment quantities...');
        $this->call(DemoRoomSeeder::class);

        // Create equipment (catalog + additional random records)
        $this->command->info('Creating equipment...');
        $this->call(EquipmentSeeder::class);
        Equipment::factory()->count(15)->create();

        // Create terms
        $this->command->info('Creating terms...');
        $terms = Term::factory()->count(3)->create();

        // Make one term current
        if ($terms->isNotEmpty()) {
            $terms->first()->update(['is_current' => true, 'status' => 'active']);
        }

        // Create schedules
        $this->command->info('Creating schedules...');
        Schedule::factory()->count(20)->create();

        $this->command->info('Database seeded successfully!');

        // Show login credentials
        $this->command->info('');
        $this->command->info('===========================');
        $this->command->info('LOGIN CREDENTIALS:');
        $this->command->info('Username: admin');
        $this->command->info('Password: password123');
        $this->command->info('===========================');

        // Show counts
        $this->command->info('');
        $this->command->info('SEEDED DATA COUNTS:');
        $this->command->info('Users: ' . UserAccount::count());
        $this->command->info('Colleges: ' . College::count());
        $this->command->info('Departments: ' . Department::count());
        $this->command->info('Buildings: ' . Building::count());
        $this->command->info('Rooms: ' . Room::count());
        $this->command->info('Equipment: ' . Equipment::count());
        $this->command->info('Terms: ' . Term::count());
        $this->command->info('Schedules: ' . Schedule::count());
    }
}
