<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $orders = $user->orders()
            ->with('items')
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        if ($order->user_id !== $user->id) {
            abort(403, 'You cannot view this order.');
        }

        return response()->json($order->load('items'));
    }

    public function checkout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $cart = $user->cart()->with('items.product')->first();

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty.'], 422);
        }

        // Re-validate stock at checkout time — stock may have changed since items were added
        foreach ($cart->items as $item) {
            /** @var \App\Models\Product $product */
            $product = $item->product;
            if (! $product->isInStock($item->quantity)) {
                return response()->json([
                    'message' => "\"{$product->name}\" no longer has enough stock for the quantity in your cart.",
                ], 422);
            }
        }

        $order = DB::transaction(function () use ($cart, $user) {
            $total = $cart->items->sum(fn ($item) => $item->quantity * $item->product->price);

            $order = $user->orders()->create([
                'total' => $total,
                'status' => 'Pending',
            ]);

            foreach ($cart->items as $item) {
                /** @var \App\Models\Product $product */
                $product = $item->product;
                $order->items()->create([
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'price'        => $product->price,
                    'quantity'     => $item->quantity,
                ]);

                // Decrement stock now that the order is confirmed
                $product->decrement('stock', $item->quantity);
            }

            // Clear the cart — checked-out items must be removed
            $cart->items()->delete();

            return $order;
        });

        return response()->json($order->load('items'), 201);
    }
}