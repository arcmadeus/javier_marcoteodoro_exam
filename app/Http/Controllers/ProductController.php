<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->select('id', 'name', 'price', 'stock', 'image_path')
            ->latest()
            ->paginate(12);

        return response()->json($products);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }
}