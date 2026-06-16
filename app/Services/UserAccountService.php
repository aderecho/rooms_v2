<?php

namespace App\Services;

use App\Models\UserAccount;
use App\Models\College;
use App\Models\Department;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Equipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserAccountService
{
    public function getAllUsers($search = null, $filters = [])
    {
        $query = UserAccount::with(['college:id,college_name', 'department:id,department_name'])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('user_type', 'like', "%{$search}%")
                  ->orWhere('account_status', 'like', "%{$search}%")
                  ->orWhereHas('college', function ($q) use ($search) {
                      $q->where('college_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('department', function ($q) use ($search) {
                      $q->where('department_name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply filters
        if (!empty($filters['user_type'])) {
            $query->where('user_type', $filters['user_type']);
        }

        if (!empty($filters['account_status'])) {
            $query->where('account_status', $filters['account_status']);
        }

        if (!empty($filters['college_id'])) {
            $query->where('college_id', $filters['college_id']);
        }

        if (!empty($filters['department_id'])) {
            $query->where('department_id', $filters['department_id']);
        }

        return $query->paginate(10);
    }

    public function getUserStats($userId = null)
    {
        $stats = [
            'total_users' => UserAccount::count(),
            'by_user_type' => UserAccount::select('user_type', DB::raw('count(*) as count'))
                ->groupBy('user_type')
                ->get()
                ->pluck('count', 'user_type')
                ->toArray(),
            'by_account_status' => UserAccount::select('account_status', DB::raw('count(*) as count'))
                ->groupBy('account_status')
                ->get()
                ->pluck('count', 'account_status')
                ->toArray(),
            'by_college' => UserAccount::select('college_id', DB::raw('count(*) as count'))
                ->whereNotNull('college_id')
                ->groupBy('college_id')
                ->with('college:id,college_name')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->college->college_name ?? 'Unknown' => $item->count];
                })
                ->toArray(),
            'recently_added' => UserAccount::orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($user) {
                    return [
                        'name' => $user->first_name . ' ' . $user->last_name,
                        'username' => $user->username,
                        'type' => $user->user_type,
                        'added_at' => $user->created_at->format('M d, Y'),
                    ];
                }),
        ];

        // Add individual user stats if user ID is provided
        if ($userId) {
            $user = UserAccount::find($userId);
            if ($user) {
                $stats['user_details'] = [
                    'assigned_rooms' => $user->assignedRooms()->count(),
                    'assigned_equipment' => $user->assignedEquipment()->count(),
                    'schedules' => $user->schedules()->count(),
                    'requested_schedules' => $user->requestedSchedules()->count(),
                    'last_login' => $user->last_login_at ? $user->last_login_at->format('M d, Y H:i') : 'Never',
                    'account_age' => $user->created_at->diffForHumans(),
                ];
            }
        }

        return $stats;
    }

    public function createUser($data)
    {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return UserAccount::create($data);
    }

    public function updateUser($userId, $data)
    {
        $user = UserAccount::findOrFail($userId);

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            // Remove password from data if not provided to avoid overwriting with null
            unset($data['password']);
        }

        $user->update($data);
        return $user;
    }

    public function deleteUser($userId)
    {
        $user = UserAccount::findOrFail($userId);

        // Check if user is assigned to any important roles
        $isDean = College::where('dean_id', $userId)->exists();
        $isDepartmentHead = Department::where('department_head_id', $userId)->exists();
        $hasAssignedRooms = $user->assignedRooms()->exists();
        $hasAssignedEquipment = $user->assignedEquipment()->exists();

        if ($isDean || $isDepartmentHead || $hasAssignedRooms || $hasAssignedEquipment) {
            throw new \Exception('Cannot delete user. User is assigned to important roles or has assigned resources.');
        }

        $user->delete();
        return true;
    }

    public function changeUserStatus($userId, $status)
    {
        $user = UserAccount::findOrFail($userId);
        $user->update(['account_status' => $status]);
        return $user;
    }

    public function updateUserProfile($userId, $data)
    {
        $user = UserAccount::findOrFail($userId);

        $allowedFields = [
            'first_name', 'last_name', 'middle_name',
            'contact_number', 'address', 'birth_date',
            'gender', 'profile_picture'
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));

        $user->update($updateData);
        return $user;
    }

    public function changePassword($userId, $currentPassword, $newPassword)
    {
        $user = UserAccount::findOrFail($userId);

        // Verify current password
        if (!Hash::check($currentPassword, $user->password)) {
            throw new \Exception('Current password is incorrect.');
        }

        // Update password
        $user->update(['password' => Hash::make($newPassword)]);
        return $user;
    }

    public function searchUsers($query)
    {
        return UserAccount::where('username', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('first_name', 'like', "%{$query}%")
            ->orWhere('last_name', 'like', "%{$query}%")
            ->orWhere('employee_id', 'like', "%{$query}%")
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'user_type' => $user->user_type,
                    'account_status' => $user->account_status,
                    'college' => $user->college ? $user->college->college_name : null,
                    'department' => $user->department ? $user->department->department_name : null,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ];
            })->toArray();
    }

    public function getDatabaseStats()
    {
        return [
            'total_users' => UserAccount::count(),
            'by_user_type' => UserAccount::select('user_type', DB::raw('count(*) as count'))
                ->groupBy('user_type')
                ->get()
                ->pluck('count', 'user_type')
                ->toArray(),
            'by_account_status' => UserAccount::select('account_status', DB::raw('count(*) as count'))
                ->groupBy('account_status')
                ->get()
                ->pluck('count', 'account_status')
                ->toArray(),
            'by_college' => UserAccount::select('college_id', DB::raw('count(*) as count'))
                ->whereNotNull('college_id')
                ->groupBy('college_id')
                ->with('college:id,college_name')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->college->college_name ?? 'Unknown' => $item->count];
                })
                ->toArray(),
            'recently_added' => UserAccount::orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($user) {
                    return $user->first_name . ' ' . $user->last_name;
                })
                ->toArray()
        ];
    }
}
