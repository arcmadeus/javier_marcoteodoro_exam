<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_creates_order_and_clears_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 25, 'stock' => 10]);
        $cart = $user->cart()->create();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 3]);

        $response = $this->actingAs($user)->postJson('/api/checkout');

        $response->assertCreated();
        $this->assertDatabaseHas('orders', ['user_id' => $user->id, 'total' => 75.00, 'status' => 'Pending']);
        $this->assertDatabaseHas('order_items', ['product_name' => $product->name, 'price' => 25.00, 'quantity' => 3]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_checkout_decrements_product_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);
        $cart = $user->cart()->create();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 4]);

        $this->actingAs($user)->postJson('/api/checkout');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 6]);
    }

    public function test_cannot_checkout_empty_cart(): void
    {
        $user = User::factory()->create();
        $user->cart()->create();

        $response = $this->actingAs($user)->postJson('/api/checkout');

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_fails_if_stock_changed_after_adding_to_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);
        $cart = $user->cart()->create();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 5]);

        // Stock reduced by something else after the item was added to cart
        $product->update(['stock' => 2]);

        $response = $this->actingAs($user)->postJson('/api/checkout');

        $response->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id]); // cart untouched
    }

    public function test_order_total_snapshot_unaffected_by_later_price_change(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 50, 'stock' => 10]);
        $cart = $user->cart()->create();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $this->actingAs($user)->postJson('/api/checkout');

        // Admin changes the price after the order was placed
        $product->update(['price' => 999]);

        $order = $user->orders()->first();
        $this->assertEquals(100.00, $order->total); // unaffected by the later price change
    }

    public function test_user_can_only_see_their_own_orders(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $cart = $userA->cart()->create();
        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($userA)->postJson('/api/checkout');

        $order = $userA->orders()->first();

        $responseA = $this->actingAs($userA)->getJson("/api/orders/{$order->id}");
        $responseA->assertOk();

        $responseB = $this->actingAs($userB)->getJson("/api/orders/{$order->id}");
        $responseB->assertForbidden();
    }
}