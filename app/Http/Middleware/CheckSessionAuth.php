<?php

namespace App\Http\Middleware;

use App\Services\AuthSessionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSessionAuth
{
    public function __construct(private readonly AuthSessionManager $sessionManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('user')) {
            return redirect('/login');
        }

        $expiresAt = $this->sessionManager->expiresAt($request) ?? $this->sessionManager->start($request);

        if (now()->timestamp >= $expiresAt) {
            $this->sessionManager->end($request);

            return redirect()->route('login')->withErrors([
                'sso' => 'Your session expired after the configured time limit. Please sign in again.',
            ]);
        }

        return $next($request);
    }
}
