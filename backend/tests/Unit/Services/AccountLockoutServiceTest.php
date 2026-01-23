<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AccountLockoutService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AccountLockoutServiceTest extends TestCase
{
    protected AccountLockoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AccountLockoutService();
        Config::set('auth.lockout.max_attempts', 5);
        Config::set('auth.lockout.duration_minutes', 30);
        Config::set('auth.lockout.attempts_window', 15);
    }

    public function test_record_failed_attempt_increments_counter()
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 0,
        ]);

        $this->service->recordFailedAttempt($user, '127.0.0.1');

        $this->assertEquals(1, $user->fresh()->failed_login_attempts);
        $this->assertNotNull($user->fresh()->last_failed_login_at);
        $this->assertEquals('127.0.0.1', $user->fresh()->last_failed_login_ip);
    }

    public function test_record_failed_attempt_locks_account_after_max_attempts()
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 4,
        ]);

        $result = $this->service->recordFailedAttempt($user, '127.0.0.1');

        $this->assertTrue($result);
        $this->assertNotNull($user->fresh()->locked_until);
    }

    public function test_record_failed_attempt_does_not_lock_before_max_attempts()
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 2,
        ]);

        $result = $this->service->recordFailedAttempt($user, '127.0.0.1');

        $this->assertFalse($result);
        $this->assertNull($user->fresh()->locked_until);
    }

    public function test_record_failed_attempt_sends_email_on_lockout()
    {
        Mail::fake();

        $user = User::factory()->create([
            'failed_login_attempts' => 4,
        ]);

        $this->service->recordFailedAttempt($user, '192.168.1.1');

        Mail::assertSent(\App\Mail\AccountLockedMail::class);
    }

    public function test_is_locked_returns_true_for_locked_account()
    {
        $user = User::factory()->create([
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->assertTrue($this->service->isLocked($user));
    }

    public function test_is_locked_returns_false_for_unlocked_account()
    {
        $user = User::factory()->create([
            'locked_until' => null,
        ]);

        $this->assertFalse($this->service->isLocked($user));
    }

    public function test_is_locked_unlocks_expired_lockout()
    {
        $user = User::factory()->create([
            'locked_until' => now()->subMinutes(5),
            'failed_login_attempts' => 5,
        ]);

        $this->assertFalse($this->service->isLocked($user));
        $this->assertNull($user->fresh()->locked_until);
        $this->assertEquals(0, $user->fresh()->failed_login_attempts);
    }

    public function test_unlock_account_resets_all_lockout_fields()
    {
        $user = User::factory()->create([
            'locked_until' => now()->addMinutes(10),
            'failed_login_attempts' => 5,
            'last_failed_login_at' => now(),
            'last_failed_login_ip' => '127.0.0.1',
        ]);

        $this->service->unlockAccount($user);

        $this->assertNull($user->fresh()->locked_until);
        $this->assertEquals(0, $user->fresh()->failed_login_attempts);
        $this->assertNull($user->fresh()->last_failed_login_at);
        $this->assertNull($user->fresh()->last_failed_login_ip);
    }

    public function test_reset_failed_attempts_after_successful_login()
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 3,
            'last_failed_login_at' => now(),
            'last_failed_login_ip' => '127.0.0.1',
        ]);

        $this->service->resetFailedAttempts($user);

        $this->assertEquals(0, $user->fresh()->failed_login_attempts);
        $this->assertNull($user->fresh()->last_failed_login_at);
        $this->assertNull($user->fresh()->last_failed_login_ip);
    }

    public function test_reset_failed_attempts_does_nothing_if_no_failures()
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 0,
        ]);

        $user->update(['last_failed_login_at' => null]);

        $this->service->resetFailedAttempts($user);

        $this->assertEquals(0, $user->fresh()->failed_login_attempts);
    }

    public function test_get_remaining_attempts()
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 2,
        ]);

        $remaining = $this->service->getRemainingAttempts($user);

        $this->assertEquals(3, $remaining);
    }

    public function test_get_remaining_attempts_returns_zero_when_locked()
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 5,
        ]);

        $remaining = $this->service->getRemainingAttempts($user);

        $this->assertEquals(0, $remaining);
    }

    public function test_get_lockout_time_remaining()
    {
        $user = User::factory()->create([
            'locked_until' => now()->addMinutes(15),
        ]);

        $remaining = $this->service->getLockoutTimeRemaining($user);

        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(15, $remaining);
    }

    public function test_get_lockout_time_remaining_returns_null_if_not_locked()
    {
        $user = User::factory()->create([
            'locked_until' => null,
        ]);

        $remaining = $this->service->getLockoutTimeRemaining($user);

        $this->assertNull($remaining);
    }

    public function test_failed_attempts_reset_outside_time_window()
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 4,
            'last_failed_login_at' => now()->subMinutes(20),
        ]);

        $this->service->recordFailedAttempt($user, '127.0.0.1');

        $this->assertEquals(1, $user->fresh()->failed_login_attempts);
    }

    public function test_custom_max_attempts_from_config()
    {
        Config::set('auth.lockout.max_attempts', 3);

        $service = new AccountLockoutService();
        $user = User::factory()->create([
            'failed_login_attempts' => 2,
        ]);

        $result = $service->recordFailedAttempt($user, '127.0.0.1');

        $this->assertTrue($result);
    }

    public function test_custom_lockout_duration_from_config()
    {
        Config::set('auth.lockout.duration_minutes', 60);

        $service = new AccountLockoutService();
        $user = User::factory()->create([
            'failed_login_attempts' => 4,
        ]);

        $service->recordFailedAttempt($user, '127.0.0.1');

        $lockoutTime = $user->fresh()->locked_until;
        $expectedTime = now()->addMinutes(60);

        $this->assertLessThan(1, $lockoutTime->diffInMinutes($expectedTime));
    }

    public function test_logs_warning_on_account_lockout()
    {
        Log::fake();

        $user = User::factory()->create([
            'failed_login_attempts' => 4,
        ]);

        $this->service->recordFailedAttempt($user, '192.168.1.1');

        Log::assertLogged('warning', function ($message, $context) {
            return str_contains($message, 'Account locked') &&
                   $context['user_id'] > 0 &&
                   isset($context['ip']);
        });
    }

    public function test_logs_info_on_failed_attempt()
    {
        Log::fake();

        $user = User::factory()->create([
            'failed_login_attempts' => 0,
        ]);

        $this->service->recordFailedAttempt($user, '127.0.0.1');

        Log::assertLogged('info', function ($message, $context) {
            return str_contains($message, 'Failed login attempt recorded') &&
                   isset($context['remaining']);
        });
    }
}
