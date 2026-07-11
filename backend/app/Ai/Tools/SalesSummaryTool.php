<?php

namespace App\Ai\Tools;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SalesSummaryTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Get an overall sales summary: total revenue, number of orders, and average order value. Optionally restrict to the last N days.';
    }

    public function handle(Request $request): Stringable|string
    {
        $days = $request->integer('days');

        $summary = Order::query()
            ->when($days > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(total_price), 0) as revenue')
            ->first();

        $period = $days > 0 ? "the last {$days} days" : 'all time';

        if ((int) $summary->orders_count === 0) {
            return "No orders recorded for {$period}.";
        }

        $unitsSold = (int) OrderItem::query()
            ->when($days > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($days)))
            ->sum('quantity');

        $revenue = number_format((float) $summary->revenue, 2);
        $average = number_format((float) $summary->revenue / (int) $summary->orders_count, 2);
        $itemsPerOrder = round($unitsSold / (int) $summary->orders_count, 1);

        return "Sales summary for {$period}: \${$revenue} total revenue across {$summary->orders_count} orders, "
            ."{$unitsSold} units sold (average order value: \${$average}, average {$itemsPerOrder} units per order).";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer(),
        ];
    }
}
