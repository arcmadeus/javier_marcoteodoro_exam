<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToCartRequest;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        /** @var Cart $cart */
        $cart = $user->cart()->firstOrCreate([]);
        $cart->load('items.product');

        /** @var \Illuminate\Database\Eloquent\Collection<int, CartItem> $items */
        $items = $cart->items;

        return response()->json([
            'items' => $items,
            'total' => $cart->total,
        ]);
    }

    public function store(AddToCartRequest $request): JsonResponse
    {
        /** @var Product $product */
        $product = Product::findOrFail($request->product_id);

        /** @var \App\Models\User $user */
        $user = $request->user();
        /** @var Cart $cart */
        $cart = $user->cart()->firstOrCreate([]);

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

    public function update(Request $request, int $itemId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        /** @var Cart $cart */
        $cart = $user->cart()->firstOrFail();
        $item = $cart->items()->findOrFail($itemId);

        $request->validate(['quantity' => ['required', 'integer', 'min:1']]);

        /** @var Product $product */
        $product = $item->product;
        if (! $product->isInStock($request->quantity)) {
            return response()->json(['message' => 'Not enough stock available.'], 422);
        }

        $item->update(['quantity' => $request->quantity]);

        return response()->json($item->load('product'));
    }

    public function destroy(Request $request, int $itemId): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        /** @var Cart $cart */
        $cart = $user->cart()->firstOrFail();
        $cart->items()->where('id', $itemId)->delete();

        return response()->json(['message' => 'Item removed from cart.']);
    }
}