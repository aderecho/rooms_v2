<?php

namespace App\Http\Middleware;

use App\Services\AuthSessionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $sessionUser = $request->session()->get('user');
        $authUser = $request->user();

        if (! $sessionUser && $authUser) {
            $sessionUser = \App\Http\Controllers\LoginController::sessionPayload($authUser);
        }

        $expiresAt = $request->session()->get(AuthSessionManager::EXPIRES_AT_KEY);
        if ($sessionUser && ! is_numeric($expiresAt)) {
            $expiresAt = app(AuthSessionManager::class)->start($request);
        }
        $durationMinutes = max(1, (int) config('auth_session.duration_minutes', 120));
        $warningMinutes = max(1, (int) config('auth_session.warning_minutes', 5));
        $pendingReservationRequests = $authUser?->user_type === 'admin' && Schema::hasTable('reservation_requests')
            ? \App\Models\ReservationRequest::query()->where('status', 'pending')->count()
            : 0;

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $sessionUser,
                'session' => $sessionUser && is_numeric($expiresAt) ? [
                    'expiresAt' => Carbon::createFromTimestamp((int) $expiresAt)->toIso8601String(),
                    'serverTime' => now()->toIso8601String(),
                    'durationMinutes' => $durationMinutes,
                    'warningSeconds' => min($warningMinutes * 60, max(1, ($durationMinutes * 60) - 1)),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'reservationNotifications' => [
                'pendingAdminCount' => $pendingReservationRequests,
            ],
        ]);
    }
}
