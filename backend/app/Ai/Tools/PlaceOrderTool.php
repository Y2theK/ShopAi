<?php

namespace App\Ai\Tools;

use App\Ai\AgentContext;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class PlaceOrderTool implements Tool
{
    private const MAX_QUANTITY_PER_ITEM = 20;

    private const MAX_DISTINCT_ITEMS = 10;

    private const MAX_ORDER_TOTAL = 10_000.0;

    private const MAX_SUGGESTIONS = 3;

    public function __construct(
        private User $user,
        private AgentContext $context
    ) {}

    public function description(): Stringable|string
    {
        return 'Place an order for one or more products on behalf of the user. Always confirm with the user before calling this.';
    }

    public function handle(Request $request): Stringable|string
    {
        $items = $request->array('items');

        if (count($items) > self::MAX_DISTINCT_ITEMS) {
            return 'Orders may contain at most '.self::MAX_DISTINCT_ITEMS.' different products. Please split the order.';
        }

        $productIds = array_map(fn ($i) => $i['product_id'], $items);

        if (count($productIds) !== count(array_unique($productIds))) {
            return 'Each product may only appear once per order. Combine duplicate lines into a single quantity.';
        }

        $products = Product::findMany($productIds)->keyBy('id');

        $total = 0;

        foreach ($items as $item) {
            $product = $products[$item['product_id']] ?? null;

            if (! $product) {
                return "Product ID {$item['product_id']} not found.";
            }

            $qty = (int) $item['quantity'];

            if ($qty < 1 || $qty > self::MAX_QUANTITY_PER_ITEM) {
                return "Quantity for {$product->name} must be between 1 and ".self::MAX_QUANTITY_PER_ITEM.'.';
            }

            if ($product->stock < $qty) {
                return "Insufficient stock for {$product->name}. Available: {$product->stock}.";
            }

            $total += (float) $product->price * $qty;
        }

        if ($total > self::MAX_ORDER_TOTAL) {
            return 'This order exceeds the $'.number_format(self::MAX_ORDER_TOTAL).' assistant checkout limit. Please place it through the store checkout instead.';
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

        Cache::tags(['products'])->flush();

        $this->context->markOrderPlaced();

        $summary = collect($items)->map(function ($item) use ($products) {
            $product = $products[$item['product_id']];

            return "{$product->name} x{$item['quantity']} (\${$product->price} each)";
        })->implode(', ');

        $result = "Your order has been placed successfully: {$order->order_code}. Items: {$summary}. Total: \${$total}. Tell the user their order code ({$order->order_code}) so they can track the order later.";

        $categoryIds = $products->pluck('category_id')->filter()->unique();

        $suggestions = Product::with('category')
            ->whereNotIn('id', $productIds)
            ->where('stock', '>', 0)
            ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->bestSelling()
            ->limit(self::MAX_SUGGESTIONS)
            ->get();

        if ($suggestions->isNotEmpty()) {
            $suggestionLines = $suggestions->map(function (Product $product) {
                $this->context->addProduct([
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                    'price' => (string) $product->price,
                    'stock' => $product->stock,
                ]);

                return "- {$product->name} (\${$product->price})";
            })->implode("\n");

            $result .= "\nPopular items the user might also like — briefly suggest these:\n{$suggestionLines}";
        }

        return $result;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()->items(
                $schema->object([
                    'product_id' => $schema->integer()->min(1)->required(),
                    'quantity' => $schema->integer()->min(1)->max(self::MAX_QUANTITY_PER_ITEM)->required(),
                ])
            )->min(1)->max(self::MAX_DISTINCT_ITEMS)->required(),
        ];
    }
}
