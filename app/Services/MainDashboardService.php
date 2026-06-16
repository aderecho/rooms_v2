<?php

namespace App\Services;

use App\Models\UserAccount;
use App\Models\Room;
use App\Models\College;
use App\Models\Department;
use App\Models\Schedule;
use App\Models\Equipment;
use App\Models\Building;
use Illuminate\Support\Facades\DB;

// Debug: Check if class is already loaded (temporarily for debugging)
if (class_exists('App\Services\MainDashboardService')) {
    die('MainDashboardService is already loaded from: ' .
        (new ReflectionClass('App\Services\MainDashboardService'))->getFileName());
}

class MainDashboardService
{
    /**
     * Get main dashboard data with all relationships
     */
    public function getMainDashboard($search = null)
    {
        // Query rooms with all necessary relationships
        $query = Room::query()
            ->with([
                'college:id,college_name',
                'user_account:id,username,email',
                'schedules:id,room_id,cfic_id,course_name,day,start_time,end_time'
            ])
            ->select('rooms.*')
            ->latest();

        // Apply search filter if provided
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('rooms.room_name', 'like', "%{$search}%")
                  ->orWhere('rooms.location', 'like', "%{$search}%")
                  ->orWhere('rooms.building', 'like', "%{$search}%")
                  ->orWhereHas('college', function ($q) use ($search) {
                      $q->where('college_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('user_account', function ($q) use ($search) {
                      $q->where('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('schedules', function ($q) use ($search) {
                      $q->where('cfic_id', 'like', "%{$search}%")
                        ->orWhere('course_name', 'like', "%{$search}%")
                        ->orWhere('day', 'like', "%{$search}%");
                  });
            });
        }

        // Return paginated results (10 items per page)
        return $query->paginate(10)->withQueryString();
    }

    /**
     * Get dashboard statistics from database
     */
    public function getDashboardStats()
    {
        try {
            return [
                'totalAccounts' => UserAccount::count(),
                'totalDepartments' => Department::count(),
                'totalColleges' => College::count(),
                'totalRooms' => Room::count(),
            ];
        } catch (\Exception $e) {
            \Log::error('Error fetching dashboard stats: ' . $e->getMessage());
            return [
                'totalAccounts' => 0,
                'totalDepartments' => 0,
                'totalColleges' => 0,
                'totalRooms' => 0,
            ];
        }
    }

    /**
     * Get additional dashboard data
     */
    public function getAdditionalDashboardData($search = null)
    {
        // You can add more queries here if needed
        return [
            'recentRooms' => Room::latest()->take(5)->get(),
            'activeUsers' => UserAccount::latest()->take(5)->get(),
        ];
    }
}
