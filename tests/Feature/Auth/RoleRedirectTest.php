<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_role_cannot_access_cms(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $response = $this->actingAs($guest)->get('/cms/dashboard');
        $response->assertForbidden();
    }
    
    public function test_admin_role_can_access_cms(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/cms/dashboard');

        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_cms(): void
    {
        $response = $this->get('/cms/dashboard');

        $response->assertRedirect('/login');
    }
}