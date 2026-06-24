<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/users');

        $response->assertOk();
        $response->assertJsonCount(4, 'data'); // 3 created + the admin itself
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/users', [
            'full_name' => 'New Guest',
            'email' => 'newguest@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'guest',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'newguest@test.com', 'role' => 'guest']);
    }

    public function test_creating_user_with_duplicate_email_fails(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'duplicate@test.com']);

        $response = $this->actingAs($admin)->postJson('/api/admin/users', [
            'full_name' => 'Another User',
            'email' => 'duplicate@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'guest',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('email');
    }

    public function test_admin_can_view_single_user(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->create();

        $response = $this->actingAs($admin)->getJson("/api/admin/users/{$guest->id}");

        $response->assertOk();
        $response->assertJsonFragment(['email' => $guest->email]);
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->create(['full_name' => 'Old Name']);

        $response = $this->actingAs($admin)->putJson("/api/admin/users/{$guest->id}", [
            'full_name' => 'Updated Name',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $guest->id, 'full_name' => 'Updated Name']);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/admin/users/{$guest->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $guest->id]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/admin/users/{$admin->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_deactivate_guest_account(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($admin)->patchJson("/api/admin/users/{$guest->id}/toggle-active");

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $guest->id, 'is_active' => false]);
    }

    public function test_admin_can_reactivate_guest_account(): void
    {
        $admin = User::factory()->admin()->create();
        $guest = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($admin)->patchJson("/api/admin/users/{$guest->id}/toggle-active");

        $response->assertOk();
        $this->assertDatabaseHas('users', ['id' => $guest->id, 'is_active' => true]);
    }

    public function test_admin_account_cannot_be_deactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $anotherAdmin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patchJson("/api/admin/users/{$anotherAdmin->id}/toggle-active");

        $response->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $anotherAdmin->id, 'is_active' => true]);
    }

    public function test_guest_cannot_access_user_management_endpoints(): void
    {
        $guest = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($guest)->getJson('/api/admin/users')->assertForbidden();
        $this->actingAs($guest)->postJson('/api/admin/users', [])->assertForbidden();
        $this->actingAs($guest)->deleteJson("/api/admin/users/{$otherUser->id}")->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_user_management(): void
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertUnauthorized(); // 401, since this is an API route using auth:sanctum
    }
}