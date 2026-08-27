<?php

namespace App\Http\Controllers;

use App\Models\UserAccount;
use App\Services\AuthSessionManager;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function logout(Request $request)
    {
        app(AuthSessionManager::class)->end($request);

        return redirect('/login');
    }

    public static function sessionPayload(UserAccount $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'name' => trim("{$user->first_name} {$user->last_name}"),
            'role' => $user->user_type,
            'permissions' => $user->roles ?? [],
        ];
    }
}
