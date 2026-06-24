<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_must_be_logged_in_to_add_to_cart(): void
    {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertUnauthorized();
    }

    public function test_user_can_add_product_to_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_adding_same_product_again_increments_quantity(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $this->actingAs($user)->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 2]);
        $response = $this->actingAs($user)->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 3]);

        $response->assertCreated();
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 5]);

        $cart = $user->cart;
        $this->assertEquals(1, $cart->items()->count()); // still one row, not two
    }

    public function test_cannot_add_out_of_stock_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 0]);

        $response = $this->actingAs($user)->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_cannot_add_more_than_available_combined_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 5]);

        $this->actingAs($user)->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 4]);
        // Already 4 in cart, adding 3 more would need 7 total, but only 5 in stock
        $response = $this->actingAs($user)->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 3]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 4]); // unchanged
    }

    public function test_user_can_view_their_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 10, 'stock' => 5]);
        $user->cart()->create()->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $response = $this->actingAs($user)->getJson('/api/cart');

        $response->assertOk();
        $response->assertJson(['total' => '20.00']);
    }

    public function test_user_can_remove_item_from_cart(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = $user->cart()->create();
        $item = $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);

        $response = $this->actingAs($user)->deleteJson("/api/cart/{$item->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }
}