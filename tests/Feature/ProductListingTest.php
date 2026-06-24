<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_view_product_listing_without_auth(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_anyone_can_view_single_product_without_auth(): void
    {
        $product = Product::factory()->create(['name' => 'Visible Product']);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Visible Product']);
    }
}