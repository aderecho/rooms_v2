<?php

use App\Models\SamlConfiguration;
use Inertia\Testing\AssertableInertia as Assert;

test('login page exposes the Google sign in entry point', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Login')
            ->where('googleLoginUrl', route('auth.google.redirect'))
        );
});

test('application shell exposes the reservation favicon', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('rel="icon"', false)
        ->assertSee('rel="apple-touch-icon"', false)
        ->assertSee('image/favicon-64.png', false)
        ->assertSee('image/apple-touch-icon.png', false);
});

test('manual password login endpoint is unavailable', function () {
    $this->post('/login', [
        'username' => 'manual-user',
        'password' => 'manual-password',
    ])->assertMethodNotAllowed();
});

test('SSO action redirects to the active institutional identity provider', function () {
    SamlConfiguration::create([
        'name' => 'UP Cebu SSO',
        'slug' => 'up-cebu-sso',
        'mode' => 'idp',
        'entity_id' => 'https://sso.upcebu.example/metadata',
        'sso_url' => 'https://sso.upcebu.example/login',
        'signing_algo' => 'rsa-sha256',
        'status' => 'active',
        'is_active' => true,
    ]);

    $response = $this->get(route('saml.login'));
    $location = $response->headers->get('Location');

    $response->assertRedirect();
    expect($location)
        ->toStartWith('https://sso.upcebu.example/login?')
        ->toContain('SAMLRequest=')
        ->toContain('RelayState=');

    $this->assertDatabaseHas('saml_replay_records', [
        'issuer' => 'https://sso.upcebu.example/metadata',
    ]);
});
