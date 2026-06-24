<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToCartRequest;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $request->user()->cart ?? $request->user()->cart()->create();
        $cart->load('items.product');

        return response()->json([
            'items' => $cart->items,
            'total' => $cart->total,
        ]);
    }

    public function store(AddToCartRequest $request)
    {
        $product = Product::findOrFail($request->product_id);

        $cart = $request->user()->cart ?? $request->user()->cart()->create();

        $existingItem = $cart->items()->where('product_id', $product->id)->first();
        $currentQtyInCart = $existingItem ? $existingItem->quantity : 0;
        $requestedTotalQty = $currentQtyInCart + $request->quantity;

        if (! $product->isInStock($requestedTotalQty)) {
            return response()->json([
                'message' => 'Not enough stock available for the requested quantity.',
            ], 422);
        }

        $item = $cart->items()->updateOrCreate(
            ['product_id' => $product->id],
            ['quantity' => $requestedTotalQty]
        );

        return response()->json($item->load('product'), 201);
    }

    public function update(Request $request, $itemId)
    {
        $cart = $request->user()->cart;
        $item = $cart->items()->findOrFail($itemId);

        $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        if (! $item->product->isInStock($request->quantity)) {
            return response()->json(['message' => 'Not enough stock available.'], 422);
        }

        $item->update(['quantity' => $request->quantity]);

        return response()->json($item->load('product'));
    }

    public function destroy(Request $request, $itemId)
    {
        $cart = $request->user()->cart;
        $cart->items()->where('id', $itemId)->delete();

        return response()->json(['message' => 'Item removed from cart.']);
    }
}