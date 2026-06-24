<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_products(): void
    {
        $admin = User::factory()->admin()->create();
        Product::factory()->count(3)->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/products');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_product_without_image(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/products', [
            'name' => 'New Product',
            'price' => 19.99,
            'stock' => 50,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('products', ['name' => 'New Product', 'price' => 19.99]);
    }

    public function test_admin_can_create_product_with_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $image = UploadedFile::fake()->image('product.jpg');

        $response = $this->actingAs($admin)->postJson('/api/admin/products', [
            'name' => 'Product With Image',
            'price' => 29.99,
            'stock' => 10,
            'image' => $image,
        ]);

        $response->assertCreated();
        $product = Product::where('name', 'Product With Image')->first();
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_creating_product_requires_valid_data(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/products', [
            'name' => '',
            'price' => -5,
            'stock' => -1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'price', 'stock']);
    }

    public function test_admin_can_update_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['stock' => 10]);

        $response = $this->actingAs($admin)->putJson("/api/admin/products/{$product->id}", [
            'stock' => 100,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 100]);
    }

    public function test_updating_product_image_deletes_old_one(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $oldImage = UploadedFile::fake()->image('old.jpg');
        $createResponse = $this->actingAs($admin)->postJson('/api/admin/products', [
            'name' => 'Product',
            'price' => 10,
            'stock' => 5,
            'image' => $oldImage,
        ]);
        $product = Product::first();
        $oldPath = $product->image_path;

        Storage::disk('public')->assertExists($oldPath);

        $newImage = UploadedFile::fake()->image('new.jpg');
        $this->actingAs($admin)->putJson("/api/admin/products/{$product->id}", [
            'image' => $newImage,
        ]);

        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_admin_can_delete_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($admin)->deleteJson("/api/admin/products/{$product->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_guest_cannot_manage_products(): void
    {
        $guest = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($guest)->postJson('/api/admin/products', [])->assertForbidden();
        $this->actingAs($guest)->putJson("/api/admin/products/{$product->id}", [])->assertForbidden();
        $this->actingAs($guest)->deleteJson("/api/admin/products/{$product->id}")->assertForbidden();
    }
}