<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_fifth_failed_attempt_locks_account_and_shows_lockout_message_immediately(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        for ($i = 1; $i <= 4; $i++) {
            $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
            $response->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        }

        // 5th attempt should show the LOCKOUT message, not the generic mismatch message
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        $response->assertSessionHasErrors(['email' => 'Too many failed attempts. Try again in a few minutes.']);

        $user->refresh();
        $this->assertTrue($user->isLocked());
    }

    public function test_locked_account_rejects_even_correct_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(5),
        ]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'correct-password']);

        $response->assertSessionHasErrors(['email' => 'Too many failed attempts. Try again in a few minutes.']);
    }

    public function test_successful_login_resets_failed_attempts(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'failed_login_attempts' => 3,
        ]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'correct-password']);

        $response->assertRedirect();

        $user->refresh();
        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }
}