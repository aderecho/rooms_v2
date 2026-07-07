<?php

use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

uses(RefreshDatabase::class);

test('google sign in redirects to google', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('scopes')->once()->andReturnSelf();
    $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com'));
    Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

    $this->get(route('auth.google.redirect'))
        ->assertRedirect('https://accounts.google.com');
});

test('an active existing account can sign in with a verified google email', function () {
    $user = UserAccount::factory()->create([
        'email' => 'faculty@up.edu.ph',
        'account_status' => 'active',
    ]);

    mockGoogleUser('FACULTY@UP.EDU.PH', true);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('main.dashboard'));

    $this->assertAuthenticatedAs($user);
    $this->assertSame($user->id, session('user.id'));
});

test('google sign in rejects an email without an existing account', function () {
    mockGoogleUser('unknown@up.edu.ph', true);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
});

test('google sign in rejects an inactive account', function () {
    UserAccount::factory()->create([
        'email' => 'inactive@up.edu.ph',
        'account_status' => 'inactive',
    ]);

    mockGoogleUser('inactive@up.edu.ph', true);

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('sso');

    $this->assertGuest();
});

function mockGoogleUser(string $email, bool $verified): void
{
    $googleUser = (new GoogleUser)->map([
        'id' => 'google-user-id',
        'email' => $email,
    ]);
    $googleUser->user = ['email_verified' => $verified];

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->once()->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);
}
