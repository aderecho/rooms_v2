<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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

        if (!$sessionUser && $authUser) {
            $sessionUser = \App\Http\Controllers\LoginController::sessionPayload($authUser);
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $sessionUser,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
