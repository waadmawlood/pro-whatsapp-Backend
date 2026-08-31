<?php

namespace Tests\Feature;

use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_user_can_login_and_receive_a_token(): void
    {
        $user = $this->makeAdmin([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
            'device_name' => 'office-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@example.com')
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('login_attempts', [
            'email' => 'admin@example.com',
            'successful' => true,
        ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->makeAdmin(['email' => 'admin@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong',
        ])->assertUnauthorized();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->makeAdmin([
            'email' => 'disabled@example.com',
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'disabled@example.com',
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = $this->makeAdmin();
        $this->actingAsUser($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_user_can_logout(): void
    {
        $user = $this->makeAdmin();

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $token = $login->json('data.token');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_multiple_devices_can_stay_logged_in(): void
    {
        $user = $this->makeAdmin();

        $first = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'computer-1',
        ])->json('data.token');

        $second = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'computer-2',
        ])->json('data.token');

        $this->withToken($first)->getJson('/api/v1/auth/me')->assertOk();
        $this->withToken($second)->getJson('/api/v1/auth/me')->assertOk();
        $this->assertSame(2, $user->tokens()->count());
    }
}
