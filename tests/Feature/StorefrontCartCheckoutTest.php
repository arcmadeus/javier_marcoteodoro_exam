<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Additional storefront tests covering:
 *  - Any registered user (guest or admin) can add to cart
 *  - Out-of-stock products cannot be added
 *  - Checkout clears cart and returns order
 *  - Cart total is correct
 */
class StorefrontCartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    // ── Cart access ────────────────────────────────────────────────────────

    public function test_any_authenticated_user_can_view_cart(): void
    {
        $user = User::factory()->create(['role' => 'guest']);

        $response = $this->actingAs($user)->getJson('/api/cart');

        $response->assertOk();
    }

    public function test_admin_can_also_add_to_cart(): void
    {
        $admin   = User::factory()->admin()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $response = $this->actingAs($admin)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id]);
    }

    public function test_unauthenticated_user_cannot_view_cart(): void
    {
        $response = $this->getJson('/api/cart');
        $response->assertUnauthorized();
    }

    // ── Out-of-stock guard ─────────────────────────────────────────────────

    public function test_out_of_stock_product_cannot_be_added_to_cart(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['stock' => 0]);

        $response = $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Not enough stock available for the requested quantity.']);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_adding_quantity_beyond_stock_is_rejected(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['stock' => 3]);

        $response = $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity'   => 10,
        ]);

        $response->assertStatus(422);
    }

    // ── Cart total ─────────────────────────────────────────────────────────

    public function test_cart_total_is_calculated_correctly(): void
    {
        $user     = User::factory()->create();
        $productA = Product::factory()->create(['price' => 20.00, 'stock' => 10]);
        $productB = Product::factory()->create(['price' =>  5.50, 'stock' => 10]);

        $cart = $user->cart()->create();
        $cart->items()->create(['product_id' => $productA->id, 'quantity' => 2]); //  40.00
        $cart->items()->create(['product_id' => $productB->id, 'quantity' => 4]); //  22.00

        $response = $this->actingAs($user)->getJson('/api/cart');

        $response->assertOk();
        // The cart endpoint returns total computed from items; accept both numeric and string format
        $body = $response->json();
        $this->assertEquals(62.00, (float) $body['total']);
    }

    // ── Checkout ───────────────────────────────────────────────────────────

    public function test_checkout_removes_items_from_cart(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['price' => 15, 'stock' => 10]);
        $cart    = $user->cart()->create();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($user)->postJson('/api/checkout')->assertCreated();

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_response_contains_order_with_items(): void
    {
        $user    = User::factory()->create();
        $product = Product::factory()->create(['price' => 30, 'stock' => 5]);
        $cart    = $user->cart()->create();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->postJson('/api/checkout');

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'status', 'total', 'items']);
        $response->assertJsonFragment(['status' => 'Pending']);
    }

    public function test_checkout_requires_authentication(): void
    {
        $this->postJson('/api/checkout')->assertUnauthorized();
    }

    // ── Order status changes (storefront view of own orders) ───────────────

    public function test_user_can_list_their_orders(): void
    {
        $user = User::factory()->create();
        $user->orders()->create(['total' => 50, 'status' => 'Pending']);
        $user->orders()->create(['total' => 25, 'status' => 'Delivered']);

        $response = $this->actingAs($user)->getJson('/api/orders');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_user_cannot_see_another_users_order(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $order = $userA->orders()->create(['total' => 99, 'status' => 'Pending']);

        $this->actingAs($userB)->getJson("/api/orders/{$order->id}")->assertForbidden();
    }
}
