<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TrackOrderTool implements Tool
{
    public function __construct(private User $user) {}

    public function description(): Stringable|string
    {
        return 'Track the status of one of the user\'s own orders by its order code (e.g. ORD-AB12CD34).';
    }

    public function handle(Request $request): Stringable|string
    {
        $code = strtoupper(trim((string) $request->string('order_code')));

        $order = Order::with('items.product')
            ->whereBelongsTo($this->user)
            ->where('order_code', $code)
            ->first();

        if (! $order) {
            return "No order with code {$code} was found on this account. Ask the user to double-check the code.";
        }

        $itemLines = $order->items->map(function ($item) {
            $name = $item->product?->name ?? 'Unavailable product';

            return "- {$name} x{$item->quantity} (\${$item->price} each)";
        })->implode("\n");

        return "Order {$order->order_code}\n"
            ."Status: {$order->status}\n"
            ."Placed: {$order->created_at->format('M j, Y g:i A')}\n"
            ."Total: \${$order->total_price}\n"
            ."Items:\n{$itemLines}";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_code' => $schema->string()->required(),
        ];
    }
}
