<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthSessionManager
{
    public const EXPIRES_AT_KEY = 'auth_session_expires_at';

    public function start(Request $request): int
    {
        $durationMinutes = max(1, (int) config('auth_session.duration_minutes', 120));
        $expiresAt = now()->addMinutes($durationMinutes)->timestamp;

        $request->session()->put(self::EXPIRES_AT_KEY, $expiresAt);

        return $expiresAt;
    }

    public function expiresAt(Request $request): ?int
    {
        $expiresAt = $request->session()->get(self::EXPIRES_AT_KEY);

        return is_numeric($expiresAt) ? (int) $expiresAt : null;
    }

    public function end(Request $request): void
    {
        Auth::logout();
        $request->session()->forget(['user', self::EXPIRES_AT_KEY]);
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
