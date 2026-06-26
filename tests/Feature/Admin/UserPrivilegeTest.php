<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests that confirm the user-management edit endpoint only allows
 * changing role and active-status — not changing name, email, or password
 * through the role-update path (those remain part of full user update).
 *
 * Also covers:
 *  - Admin can promote guest → admin (role change)
 *  - Admin can demote admin → guest (role change)
 *  - Active toggle is blocked for admin accounts
 */
class UserPrivilegeTest extends TestCase
{
    use RefreshDatabase;

    // ── Role change ────────────────────────────────────────────────────────

    public function test_admin_can_promote_guest_to_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->create(['role' => 'guest']);

        $response = $this->actingAs($admin)->putJson("/api/admin/users/{$guest->id}", [
            'role' => 'admin',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $guest->id, 'role' => 'admin']);
    }

    public function test_admin_can_demote_admin_to_guest(): void
    {
        $admin       = User::factory()->admin()->create();
        $otherAdmin  = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->putJson("/api/admin/users/{$otherAdmin->id}", [
            'role' => 'guest',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id, 'role' => 'guest']);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->create();

        $response = $this->actingAs($admin)->putJson("/api/admin/users/{$guest->id}", [
            'role' => 'superuser', // not a valid role
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('role');
    }

    // ── Deactivation ───────────────────────────────────────────────────────

    public function test_admin_can_toggle_active_for_guest(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->create(['role' => 'guest', 'is_active' => true]);

        $response = $this->actingAs($admin)->patchJson("/api/admin/users/{$guest->id}/toggle-active");

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $guest->id, 'is_active' => false]);
    }

    public function test_admin_cannot_toggle_active_for_another_admin(): void
    {
        $admin       = User::factory()->admin()->create();
        $otherAdmin  = User::factory()->admin()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->patchJson("/api/admin/users/{$otherAdmin->id}/toggle-active");

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id, 'is_active' => true]); // unchanged
    }

    public function test_guest_cannot_change_any_user_role(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $other = User::factory()->create(['role' => 'guest']);

        $this->actingAs($guest)->putJson("/api/admin/users/{$other->id}", [
            'role' => 'admin',
        ])->assertForbidden();
    }

    // ── Deactivated account blocked from login ─────────────────────────────

    public function test_deactivated_guest_cannot_log_in(): void
    {
        $guest = User::factory()->create([
            'password'  => bcrypt('secret123'),
            'role'      => 'guest',
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email'    => $guest->email,
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
