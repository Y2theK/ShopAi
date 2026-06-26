<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PlaceOrderTool implements Tool
{
    public function __construct(private User $user) {}

    public function description(): Stringable|string
    {
        return 'Place an order for one or more products on behalf of the user. Always confirm with the user before calling this.';
    }

    public function handle(Request $request): Stringable|string
    {
        $items = $request->array('items');

        $productIds = array_map(fn ($i) => $i['product_id'], $items);
        $products = Product::findMany($productIds)->keyBy('id');

        $total = 0;

        foreach ($items as $item) {
            $product = $products[$item['product_id']] ?? null;

            if (! $product) {
                return "Product ID {$item['product_id']} not found.";
            }

            $qty = (int) $item['quantity'];

            if ($product->stock < $qty) {
                return "Insufficient stock for {$product->name}. Available: {$product->stock}.";
            }

            $total += (float) $product->price * $qty;
        }

        $order = DB::transaction(function () use ($items, $products, $total) {
            $order = Order::create([
                'user_id' => $this->user->id,
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

        $summary = collect($items)->map(function ($item) use ($products) {
            $product = $products[$item['product_id']];

            return "{$product->name} x{$item['quantity']} (\${$product->price} each)";
        })->implode(', ');

        return "Order #{$order->id} placed successfully! Items: {$summary}. Total: \${$total}.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()->items(
                $schema->object([
                    'product_id' => $schema->integer()->required(),
                    'quantity' => $schema->integer()->required(),
                ])
            )->min(1)->required(),
        ];
    }
}
