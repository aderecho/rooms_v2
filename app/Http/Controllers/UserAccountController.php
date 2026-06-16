<?php

namespace App\Http\Controllers;

use App\Models\UserAccount;
use App\Models\College;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserAccountController extends Controller
{
    private function getSessionRole(Request $request): string
    {
        return strtolower((string) data_get($request->session()->get('user'), 'role', ''));
    }

    private function canManageUsers(Request $request): bool
    {
        $role = $this->getSessionRole($request);
        return in_array($role, ['admin', 'sysadmin'], true);
    }

    private function ensureCanManageUsers(Request $request)
    {
        if ($this->canManageUsers($request)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Only admins can manage user accounts.',
        ], 403);
    }

    // Render the user account management page using Inertia
    public function index(Request $request)
    {
        if (!$this->canManageUsers($request)) {
            return redirect()->route('main.dashboard')
                ->with('error', 'You do not have permission to manage user accounts.');
        }

        $query = UserAccount::with(['college', 'department']);

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('middle_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('college_id')) {
            $query->where('college_id', $request->input('college_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->input('user_type'));
        }

        if ($request->filled('account_status')) {
            $query->where('account_status', $request->input('account_status'));
        }

        // Get users with relationships (frontend handles pagination UI)
        $users = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

// $users = UserAccount::with(['college', 'department'])->get();

//     dd($users);

        // Get colleges and departments for dropdowns
        $colleges = College::select('id', 'college_name')->get();
        $departments = Department::select('id', 'department_name', 'college_id')->get();

        // Get stats
        $stats = $this->getStats();

        // Transform users for frontend
        $transformedUsers = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'middle_name' => $user->middle_name,
                'employee_id' => $user->employee_id,
                'profile_picture' => $user->profile_picture,
                'gender' => $user->gender,
                'birth_date' => $user->birth_date,
                'contact_number' => $user->contact_number,
                'address' => $user->address,
                'role' => $user->user_type,
                'account_status' => $user->account_status,
                'department' => $user->department ? $user->department->department_name : null,
                'college' => $user->college ? $user->college->college_name : null,
                'college_id' => $user->college_id,
                'department_id' => $user->department_id,
                'created_at' => $user->created_at ? $user->created_at->format('Y-m-d H:i:s') : null,
                'updated_at' => $user->updated_at ? $user->updated_at->format('Y-m-d H:i:s') : null,
                'permissions' => $user->roles ?? [],
            ];
        })->toArray();

        return Inertia::render('UserAccountPage', [
            'initialUsers' => $transformedUsers,
            'colleges' => $colleges,
            'departments' => $departments,
            'stats' => $stats,
            'canManageUsers' => $this->canManageUsers($request),
            'pagination' => [
                'total' => $users->count(),
                'per_page' => $users->count(),
                'current_page' => 1,
                'last_page' => 1,
                'from' => $users->count() > 0 ? 1 : null,
                'to' => $users->count(),
            ],
            'filters' => $request->only(['search', 'college_id', 'department_id', 'user_type', 'account_status'])
        ]);
    }

    // Get stats for the dashboard
    private function getStats()
    {
        return [
            'total' => UserAccount::count(),
            'active' => UserAccount::where('account_status', 'active')->count(),
            'inactive' => UserAccount::where('account_status', 'inactive')->count(),
            'suspended' => UserAccount::where('account_status', 'suspended')->count(),
            'pending' => UserAccount::where('account_status', 'pending')->count(),
            'by_user_type' => UserAccount::select('user_type', DB::raw('COUNT(*) as count'))
                ->groupBy('user_type')
                ->get()
                ->pluck('count', 'user_type')
                ->toArray(),
        ];
    }

    // Store new user
    public function store(Request $request)
    {
        if ($resp = $this->ensureCanManageUsers($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:user_accounts,username',
            'email' => 'required|email|max:255|unique:user_accounts,email',
            'password' => 'required|string|min:8',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'employee_id' => 'nullable|string|max:50|unique:user_accounts,employee_id',
            'user_type' => 'required|in:admin,faculty,staff,student,guest',
            'account_status' => 'required|in:active,inactive,suspended,pending',
            'college_id' => 'nullable|exists:colleges,id',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['roles'] = $this->resolvePermissions($request, $validated['user_type']);

        $user = UserAccount::create($validated);
        $user->load(['college', 'department']);

        return response()->json([
            'success' => true,
            'message' => 'User account created successfully!',
            'user' => $this->transformUser($user)
        ]);
    }

    // Update user
    public function update(Request $request, $id)
    {
        if ($resp = $this->ensureCanManageUsers($request)) {
            return $resp;
        }

        $user = UserAccount::findOrFail($id);

        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:user_accounts,username,' . $user->id,
            'email' => 'required|email|max:255|unique:user_accounts,email,' . $user->id,
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'middle_name' => 'nullable|string|max:100',
            'employee_id' => 'nullable|string|max:50|unique:user_accounts,employee_id,' . $user->id,
            'user_type' => 'required|in:admin,faculty,staff,student,guest',
            'account_status' => 'required|in:active,inactive,suspended,pending',
            'college_id' => 'nullable|exists:colleges,id',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        // Handle password update if provided
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        }

        if ($request->has('permissions')) {
            $validated['roles'] = $this->resolvePermissions($request, $validated['user_type']);
        }

        $user->update($validated);
        $user->load(['college', 'department']);

        return response()->json([
            'success' => true,
            'message' => 'User account updated successfully!',
            'user' => $this->transformUser($user)
        ]);
    }

    // Delete user
    public function destroy(Request $request, $id)
    {
        if ($resp = $this->ensureCanManageUsers($request)) {
            return $resp;
        }

        $user = UserAccount::findOrFail($id);
        $username = $user->username;
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User account deleted successfully!',
            'username' => $username
        ]);
    }

    // Helper method to transform user for response
    private function transformUser($user)
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'role' => $user->user_type,
            'department' => $user->department ? $user->department->department_name : null,
            'college' => $user->college ? $user->college->college_name : null,
            'account_status' => $user->account_status,
            'permissions' => $user->roles ?? [],
        ];
    }

    private function resolvePermissions(Request $request, string $userType): array
    {
        $allowed = ['Can Approve', 'Can Edit', 'Can Book', 'Staff Work', 'User Type Only'];

        if ($request->has('permissions') && is_array($request->input('permissions'))) {
            $selected = array_values(array_intersect($request->input('permissions'), $allowed));

            if (!empty($selected)) {
                return $selected;
            }
        }

        return $this->getDefaultPermissions($userType);
    }

    // Get default permissions based on user type
    private function getDefaultPermissions($userType)
    {
        $permissions = [
            'admin' => ['Can Approve', 'Can Edit', 'Can Book', 'Staff Work', 'User Type Only'],
            'staff' => ['Can Book', 'Staff Work'],
            'faculty' => ['Can Book', 'User Type Only'],
            'student' => ['Can Book', 'User Type Only'],
            'guest' => ['User Type Only']
        ];

        return $permissions[$userType] ?? ['User Type Only'];
    }
}
