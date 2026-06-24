<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_all_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Order::create(['user_id' => $userA->id, 'total' => 50, 'status' => 'Pending']);
        Order::create(['user_id' => $userB->id, 'total' => 75, 'status' => 'Pending']);

        $response = $this->actingAs($admin)->getJson('/api/admin/orders');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $order = Order::create(['user_id' => $user->id, 'total' => 50, 'status' => 'Pending']);

        $response = $this->actingAs($admin)->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'For Delivery',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'For Delivery']);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $order = Order::create(['user_id' => $user->id, 'total' => 50, 'status' => 'Pending']);

        $response = $this->actingAs($admin)->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => 'Shipped', // not one of the four valid statuses
        ]);

        $response->assertStatus(422);
    }

    public function test_guest_cannot_access_admin_order_endpoints(): void
    {
        $guest = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $guest->id]);

        $this->actingAs($guest)->getJson('/api/admin/orders')->assertForbidden();
        $this->actingAs($guest)->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'Delivered'])->assertForbidden();
    }
}