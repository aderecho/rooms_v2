<?php

namespace App\Http\Controllers;

use App\Models\UserAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $exception) {
            return $this->failed('Your Google sign-in session expired. Please try again.');
        } catch (Throwable $exception) {
            Log::warning('Google SSO callback failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->failed('Google sign-in could not be completed. Please try again.');
        }

        $email = mb_strtolower(trim((string) $googleUser->getEmail()));
        $rawUser = $googleUser->getRaw();

        if ($email === '' || ($rawUser['email_verified'] ?? false) !== true) {
            return $this->failed('Google did not provide a verified email address.');
        }

        $user = UserAccount::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $user) {
            return $this->failed('No room management account is registered for this Google email.');
        }

        if ($user->account_status !== 'active') {
            return $this->failed('Your account is not active. Please contact an administrator.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('user', LoginController::sessionPayload($user));

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return redirect()->route('main.dashboard');
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['sso' => $message]);
    }
}
