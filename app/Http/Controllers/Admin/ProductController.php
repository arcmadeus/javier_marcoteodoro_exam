<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()->latest()->paginate(10);

        return response()->json($products);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->only(['name', 'price', 'stock']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        } elseif ($request->filled('image_url')) {
            $data['image_path'] = $request->input('image_url');
        }

        $product = Product::create($data);

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->only(['name', 'price', 'stock']);

        if ($request->hasFile('image')) {
            // Delete old stored file (skip if it's a URL)
            if ($product->image_path && !str_starts_with($product->image_path, 'http')) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->file('image')->store('products', 'public');
        } elseif ($request->filled('image_url')) {
            // Delete old stored file if replacing with URL
            if ($product->image_path && !str_starts_with($product->image_path, 'http')) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $request->input('image_url');
        }

        $product->update($data);

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        // Only delete from storage if it's a local file, not a URL
        if ($product->image_path && !str_starts_with($product->image_path, 'http')) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }
}