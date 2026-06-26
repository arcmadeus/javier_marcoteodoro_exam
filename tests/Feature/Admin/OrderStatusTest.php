<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Extended order-management tests covering all four valid statuses and
 * that each transition persists correctly to the database.
 */
class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $status = 'Pending'): Order
    {
        $user = User::factory()->create();
        return Order::create(['user_id' => $user->id, 'total' => 100, 'status' => $status]);
    }

    // ── All four valid status transitions ──────────────────────────────────

    public function test_admin_can_set_status_to_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder('For Delivery');

        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'Pending'])
            ->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Pending']);
    }

    public function test_admin_can_set_status_to_for_delivery(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder('Pending');

        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'For Delivery'])
            ->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'For Delivery']);
    }

    public function test_admin_can_set_status_to_delivered(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder('For Delivery');

        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'Delivered'])
            ->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Delivered']);
    }

    public function test_admin_can_set_status_to_canceled(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder('Pending');

        $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'Canceled'])
            ->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Canceled']);
    }

    // ── Validation ─────────────────────────────────────────────────────────

    public function test_only_valid_statuses_are_accepted(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder();

        foreach (['shipped', 'processing', '', 'PENDING'] as $badStatus) {
            $this->actingAs($admin)
                ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => $badStatus])
                ->assertStatus(422);
        }
    }

    // ── Response shape ─────────────────────────────────────────────────────

    public function test_update_status_response_includes_items_relation(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder();

        $response = $this->actingAs($admin)
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'Delivered']);

        $response->assertOk();
        $response->assertJsonStructure(['id', 'status', 'total', 'items']);
    }

    // ── Admin can filter orders by status ──────────────────────────────────

    public function test_admin_can_filter_orders_by_status(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();

        Order::create(['user_id' => $user->id, 'total' => 10, 'status' => 'Pending']);
        Order::create(['user_id' => $user->id, 'total' => 20, 'status' => 'Delivered']);
        Order::create(['user_id' => $user->id, 'total' => 30, 'status' => 'Canceled']);

        $response = $this->actingAs($admin)->getJson('/api/admin/orders?status=Pending');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment(['status' => 'Pending']);
    }

    // ── Access control ─────────────────────────────────────────────────────

    public function test_guest_cannot_update_any_order_status(): void
    {
        $guest = User::factory()->create(['role' => 'guest']);
        $order = $this->makeOrder();

        $this->actingAs($guest)
            ->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'Delivered'])
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_update_order_status(): void
    {
        $order = $this->makeOrder();

        $this->patchJson("/api/admin/orders/{$order->id}/status", ['status' => 'Delivered'])
            ->assertUnauthorized();
    }
}
