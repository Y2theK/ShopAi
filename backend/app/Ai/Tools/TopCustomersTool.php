<?php

namespace App\Ai\Tools;

use App\Ai\ChartContext;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class TopCustomersTool implements Tool
{
    public function __construct(private ChartContext $context) {}

    public function description(): Stringable|string
    {
        return 'Get the top customers ranked by total amount spent, with their order counts. Optionally limit the number of customers (default 5).';
    }

    public function handle(Request $request): Stringable|string
    {
        $limit = max(1, $request->integer('limit', 5));

        $customers = User::query()
            ->withSum('orders as total_spent', 'total_price')
            ->withCount('orders')
            ->has('orders')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        if ($customers->isEmpty()) {
            return 'No customers have placed an order yet.';
        }

        $this->context->addChart([
            'type' => 'bar',
            'title' => 'Top Customers by Spend',
            'labels' => $customers->pluck('name')->all(),
            'datasets' => [
                ['label' => 'Total Spent ($)', 'data' => $customers->pluck('total_spent')->map(fn ($v) => round((float) $v, 2))->all()],
            ],
        ]);

        $lines = [];

        foreach ($customers as $customer) {
            $spent = number_format((float) $customer->total_spent, 2);
            $lines[] = "{$customer->name} ({$customer->email}): \${$spent} across {$customer->orders_count} orders";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer(),
        ];
    }
}
