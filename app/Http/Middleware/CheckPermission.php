<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect('/login');
        }

        // Check if user has any of the required permissions
        $userPermissions = $user->permissions ?? [];

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return $next($request);
            }
        }

        // If no permission, redirect to appropriate page based on role
        return redirect($this->getDefaultRoute($user->role));
    }

    private function getDefaultRoute($role)
    {
        switch ($role) {
            case 'Faculty':
            case 'USER':
                return '/schedule';
            default:
                return '/MainDashboard';
        }
    }
}
