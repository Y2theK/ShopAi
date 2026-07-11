<?php

namespace App\Ai\Tools;

use App\Ai\ChartContext;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CustomerSummaryTool implements Tool
{
    public function __construct(private ChartContext $context) {}

    public function description(): Stringable|string
    {
        return 'Get a customer overview: total customers, new signups this month, monthly signups for the last N months (default 6), customers who have never ordered, and the repeat-purchase rate.';
    }

    public function handle(Request $request): Stringable|string
    {
        $months = max(1, $request->integer('months', 6));

        $totalCustomers = User::count();

        if ($totalCustomers === 0) {
            return 'There are no registered customers yet.';
        }

        $newThisMonth = User::where('created_at', '>=', now()->startOfMonth())->count();
        $withoutOrders = User::doesntHave('orders')->count();

        $orderCounts = Order::query()
            ->selectRaw('user_id, COUNT(*) as orders_count')
            ->groupBy('user_id')
            ->pluck('orders_count');

        $buyers = $orderCounts->count();
        $repeatBuyers = $orderCounts->filter(fn ($count) => $count > 1)->count();
        $repeatRate = $buyers > 0 ? round($repeatBuyers / $buyers * 100, 1) : 0.0;

        $signups = User::query()
            ->where('created_at', '>=', now()->subMonths($months - 1)->startOfMonth())
            ->selectRaw("strftime('%Y-%m', created_at) as month")
            ->selectRaw('COUNT(*) as signups')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        if ($signups->isNotEmpty()) {
            $this->context->addChart([
                'type' => 'bar',
                'title' => "New Customer Signups (last {$months} months)",
                'labels' => $signups->pluck('month')->all(),
                'datasets' => [
                    ['label' => 'Signups', 'data' => $signups->pluck('signups')->map(fn ($v) => (int) $v)->all()],
                ],
            ]);
        }

        return "Customer summary: {$totalCustomers} registered customers, "
            ."{$newThisMonth} new this month, "
            ."{$withoutOrders} have never placed an order, "
            ."{$buyers} have ordered at least once, "
            ."repeat-purchase rate {$repeatRate}% ({$repeatBuyers} customers ordered more than once).";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'months' => $schema->integer(),
        ];
    }
}
