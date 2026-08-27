<?php

use App\Http\Controllers\LoginController;
use App\Models\UserAccount;
use App\Services\AuthSessionManager;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated session receives the configured absolute expiration', function () {
    config()->set('auth_session.duration_minutes', 120);
    $user = UserAccount::factory()->create(['account_status' => 'active']);
    $startedAt = now()->timestamp;

    $response = $this->actingAs($user)
        ->withSession(['user' => LoginController::sessionPayload($user)])
        ->get(route('main.dashboard'));

    $response
        ->assertOk()
        ->assertSessionHas(AuthSessionManager::EXPIRES_AT_KEY)
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.session.durationMinutes', 120)
            ->where('auth.session.warningSeconds', 300)
            ->has('auth.session.expiresAt')
            ->has('auth.session.serverTime')
        );

    $expiresAt = (int) session(AuthSessionManager::EXPIRES_AT_KEY);
    expect($expiresAt)->toBeGreaterThanOrEqual($startedAt + 7199)
        ->toBeLessThanOrEqual($startedAt + 7201);
});

test('authenticated activity does not extend the absolute expiration', function () {
    $user = UserAccount::factory()->create(['account_status' => 'active']);
    $expiresAt = now()->addMinutes(30)->timestamp;

    $this->actingAs($user)
        ->withSession([
            'user' => LoginController::sessionPayload($user),
            AuthSessionManager::EXPIRES_AT_KEY => $expiresAt,
        ])
        ->get(route('main.dashboard'))
        ->assertOk()
        ->assertSessionHas(AuthSessionManager::EXPIRES_AT_KEY, $expiresAt);
});

test('expired authenticated session is invalidated and redirected to SSO login', function () {
    $user = UserAccount::factory()->create(['account_status' => 'active']);

    $this->actingAs($user)
        ->withSession([
            'user' => LoginController::sessionPayload($user),
            AuthSessionManager::EXPIRES_AT_KEY => now()->subSecond()->timestamp,
        ])
        ->get(route('main.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso')
        ->assertSessionMissing('user');

    $this->assertGuest();
});
