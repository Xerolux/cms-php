<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $apiVersion = 'api/v1';

    public function test_user_can_register_with_valid_data()
    {
        $response = $this->postJson("{$this->apiVersion}/auth/register", [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                ],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);
    }

    public function test_registration_requires_name()
    {
        $response = $this->postJson("{$this->apiVersion}/auth/register", [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_registration_requires_email()
    {
        $response = $this->postJson("{$this->apiVersion}/auth/register", [
            'name' => 'Test User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_valid_email()
    {
        $response = $this->postJson("{$this->apiVersion}/auth/register", [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_unique_email()
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson("{$this->apiVersion}/auth/register", [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_requires_password_confirmation()
    {
        $response = $this->postJson("{$this->apiVersion}/auth/register", [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_password_must_match_confirmation()
    {
        $response = $this->postJson("{$this->apiVersion}/auth/register", [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson("{$this->apiVersion}/auth/login", [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                ],
                'token',
            ]);
    }

    public function test_login_requires_email()
    {
        $response = $this->postJson("{$this->apiVersion}/auth/login", [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_password()
    {
        $response = $this->postJson("{$this->apiVersion}/auth/login", [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_fails_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson("{$this->apiVersion}/auth/login", [
            'email' => $user->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_with_nonexistent_user()
    {
        $response = $this->postJson("{$this->apiVersion}/auth/login", [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson("{$this->apiVersion}/auth/logout");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_logout()
    {
        $response = $this->postJson("{$this->apiVersion}/auth/logout");

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_profile()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson("{$this->apiVersion}/auth/profile");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_get_profile()
    {
        $response = $this->getJson("{$this->apiVersion}/auth/profile");

        $response->assertStatus(401);
    }

    public function test_password_reset_request_sends_email()
    {
        Notification::fake();
        $user = User::factory()->create();

        $response = $this->postJson("{$this->apiVersion}/auth/password/reset-request", [
            'email' => $user->email,
        ]);

        $response->assertStatus(200);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_request_with_nonexistent_email()
    {
        // Don't reveal if email exists or not for security
        $response = $this->postJson("{$this->apiVersion}/auth/password/reset-request", [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function test_password_reset_with_valid_token()
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson("{$this->apiVersion}/auth/password/reset", [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_password_reset_requires_token()
    {
        $response = $this->postJson("{$this->apiVersion}/auth/password/reset", [
            'email' => 'test@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_password_reset_requires_password_confirmation()
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson("{$this->apiVersion}/auth/password/reset", [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_authenticated_user_can_update_profile()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->putJson("{$this->apiVersion}/auth/profile", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Updated Name',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_can_change_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson("{$this->apiVersion}/auth/password/change", [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_password_change_requires_correct_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson("{$this->apiVersion}/auth/password/change", [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }
}
