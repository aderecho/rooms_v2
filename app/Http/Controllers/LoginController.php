<?php

namespace App\Http\Controllers;

use App\Models\UserAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = trim($credentials['username']);

        $user = UserAccount::where('username', $login)
            ->orWhere('email', $login)
            ->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors([
                'username' => 'Invalid username or password.',
            ]);
        }

        if ($user->account_status !== 'active') {
            return back()->withErrors([
                'username' => 'Your account is not active. Please contact an administrator.',
            ]);
        }

        Auth::login($user);

        $request->session()->put('user', $this->sessionPayload($user));

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect('/MainDashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->forget('user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

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
