<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));

        $orders = $request->user()->orders()
            ->with('items.product')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return $this->successResponse([
            'data' => OrderResource::collection($orders)->resolve(),
            'meta' => $this->getpaginatedMeta($orders),
        ], 'Orders retrieved successfully!');
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $items = $payload['items'];
        $productIds = array_map(fn ($i) => $i['product_id'], $items);

        $products = Product::findMany($productIds)->keyBy('id');

        $total = 0;

        foreach ($items as $item) {
            $product = $products[$item['product_id']];
            $qty = (int) $item['quantity'];

            if ($product->stock < $qty) {
                return $this->errorResponse("Insufficient stock for product ({$product->name})!", 400);
            }

            $total += (float) $product->price * $qty;
        }

        $order = DB::transaction(function () use ($request, $items, $products, $total) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'total_price' => $total,
            ]);

            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                $qty = (int) $item['quantity'];

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'price' => $product->price,
                ]);

                $product->decrement('stock', $qty);
            }

            return $order;
        });

        Cache::tags(['products'])->flush();

        if (! $order) {
            return $this->errorResponse('Failed to create order!', 500);
        }

        $order->load('items.product');

        return $this->successResponse(
            new OrderResource($order),
            "Your order has been placed successfully: {$order->order_code}",
            201
        );
    }
}
