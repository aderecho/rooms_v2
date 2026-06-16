<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserAccountController extends Controller
{
    // Get all users
    public function index()
    {
        $users = UserAccount::orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    // Create new user
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:user_accounts,username',
            'email' => 'required|email|max:100|unique:user_accounts,email',
            'password' => 'required|string|min:6',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'role' => 'required|string',
            'department' => 'required|string|max:100',
            'college' => 'required|string|max:150'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = UserAccount::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'role' => $request->role,
            'department' => $request->department,
            'college' => $request->college,
            'permissions' => $request->permissions ?? []
        ]);

        return response()->json([
            'success' => true,
            'user' => $user
        ], 201);
    }

    // Update user
    public function update(Request $request, UserAccount $userAccount)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:user_accounts,username,' . $userAccount->id,
            'email' => 'required|email|max:100|unique:user_accounts,email,' . $userAccount->id,
            'password' => 'nullable|string|min:6',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'role' => 'required|string',
            'department' => 'required|string|max:100',
            'college' => 'required|string|max:150'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [
            'username' => $request->username,
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'role' => $request->role,
            'department' => $request->department,
            'college' => $request->college,
            'permissions' => $request->permissions ?? $userAccount->permissions
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $userAccount->update($data);

        return response()->json([
            'success' => true,
            'user' => $userAccount
        ]);
    }

    // Delete user
    public function destroy(UserAccount $userAccount)
    {
        $userAccount->delete();

        return response()->json([
            'success' => true,
            'username' => $userAccount->username
        ]);
    }
}
