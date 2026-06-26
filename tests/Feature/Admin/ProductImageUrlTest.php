<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for product image URL support — an alternative to file-upload for
 * adding product images.
 */
class ProductImageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_product_with_image_url(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/products', [
            'name'      => 'URL Product',
            'price'     => 10.00,
            'stock'     => 5,
            'image_url' => 'https://example.com/image.jpg',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('products', [
            'name'       => 'URL Product',
            'image_path' => 'https://example.com/image.jpg',
        ]);
    }

    public function test_admin_can_update_product_with_image_url(): void
    {
        $admin   = User::factory()->admin()->create();
        $product = Product::factory()->create(['image_path' => null]);

        $response = $this->actingAs($admin)->putJson("/api/admin/products/{$product->id}", [
            'image_url' => 'https://example.com/new-image.jpg',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('products', [
            'id'         => $product->id,
            'image_path' => 'https://example.com/new-image.jpg',
        ]);
    }

    public function test_invalid_url_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/admin/products', [
            'name'      => 'Bad URL Product',
            'price'     => 10.00,
            'stock'     => 5,
            'image_url' => 'not-a-valid-url',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('image_url');
    }

    public function test_uploading_new_file_removes_old_local_file(): void
    {
        Storage::fake('public');
        $admin   = User::factory()->admin()->create();
        $oldFile = UploadedFile::fake()->image('old.jpg');

        // Create product with a local file
        $this->actingAs($admin)->postJson('/api/admin/products', [
            'name'  => 'File Product',
            'price' => 10,
            'stock' => 5,
            'image' => $oldFile,
        ]);

        $product = Product::where('name', 'File Product')->firstOrFail();
        $oldPath = $product->image_path;
        Storage::disk('public')->assertExists($oldPath);

        // Replace with a URL — old file should be deleted from storage
        $this->actingAs($admin)->putJson("/api/admin/products/{$product->id}", [
            'image_url' => 'https://cdn.example.com/img.png',
        ]);

        Storage::disk('public')->assertMissing($oldPath);
        $this->assertDatabaseHas('products', [
            'id'         => $product->id,
            'image_path' => 'https://cdn.example.com/img.png',
        ]);
    }

    public function test_deleting_product_with_url_image_does_not_throw(): void
    {
        $admin   = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'image_path' => 'https://example.com/remote.jpg',
        ]);

        // Deleting a product whose image is a URL should not try to delete from Storage
        $response = $this->actingAs($admin)->deleteJson("/api/admin/products/{$product->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
