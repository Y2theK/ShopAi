<?php

namespace App\Ai\Tools;

use App\Ai\AgentContext;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TrackOrderTool implements Tool
{
    public function __construct(
        private User $user,
        private AgentContext $context
    ) {}

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

        $result = "Order {$order->order_code}\n"
            ."Status: {$order->status}\n"
            ."Placed: {$order->created_at->format('M j, Y g:i A')}\n"
            ."Total: \${$order->total_price}\n"
            ."Items:\n{$itemLines}";

        if ($order->address) {
            $this->context->setOrderInfo([
                'order_code' => $order->order_code,
                'status' => $order->status,
                'phone' => $order->phone,
                'secondary_phone' => $order->secondary_phone,
                'address' => $order->address,
                'city' => $order->city,
                'state' => $order->state,
                'country' => $order->country,
            ]);

            $result .= "\nThe delivery address is shown to the user separately — do not ask for it.";
        }

        return $result;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_code' => $schema->string()->required(),
        ];
    }
}
