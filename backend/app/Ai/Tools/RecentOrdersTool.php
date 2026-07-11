<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Traits\MasksPii;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RecentOrdersTool implements Tool
{
    use MasksPii;

    public function description(): Stringable|string
    {
        return 'List the most recent orders with customer, total, item count, and date. Optionally limit the number of orders (default 10), restrict to the last N days, or filter by customer name or email.';
    }

    public function handle(Request $request): Stringable|string
    {
        $limit = max(1, $request->integer('limit', 10));
        $days = $request->integer('days');
        $customer = trim((string) $request->string('customer'));

        $orders = Order::query()
            ->with('user:id,name,email')
            ->withCount('items')
            ->when($days > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->when($customer !== '', fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where(fn ($match) => $match
                    ->where('name', 'like', "%{$customer}%")
                    ->orWhere('email', 'like', "%{$customer}%"))))
            ->latest()
            ->limit($limit)
            ->get();

        if ($orders->isEmpty()) {
            return 'No orders found for the requested criteria.';
        }

        $lines = [];

        foreach ($orders as $order) {
            $total = number_format((float) $order->total_price, 2);
            $customerName = $order->user ? "{$order->user->name} ({$this->maskEmail($order->user->email)})" : 'Unknown customer';
            $date = $order->created_at->format('Y-m-d H:i');

            $lines[] = "Order #{$order->id} — {$customerName}: \${$total}, {$order->items_count} item(s), placed {$date}";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer(),
            'days' => $schema->integer(),
            'customer' => $schema->string(),
        ];
    }
}
