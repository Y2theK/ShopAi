<?php

namespace App\Ai\Tools;

use App\Ai\ChartContext;
use App\Ai\Concerns\CachesToolResults;
use App\Models\Order;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class MonthlySalesTrendTool implements Tool
{
    use CachesToolResults;

    public function __construct(private ChartContext $context) {}

    public function description(): Stringable|string
    {
        return 'Get the monthly sales trend: revenue and order count per month for the last N months (default 6).';
    }

    public function handle(Request $request): Stringable|string
    {
        $months = max(1, $request->integer('months', 6));

        return $this->cached(
            "admin-tools:monthly-sales-trend:{$months}",
            fn () => $this->compute($months),
            300,
            $this->context,
        );
    }

    private function compute(int $months): string
    {
        $rows = Order::query()
            ->where('created_at', '>=', now()->subMonths($months - 1)->startOfMonth())
            ->selectRaw("strftime('%Y-%m', created_at) as month")
            ->selectRaw('SUM(total_price) as revenue')
            ->selectRaw('COUNT(*) as orders_count')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        if ($rows->isEmpty()) {
            return "No orders recorded in the last {$months} months.";
        }

        $this->context->addChart([
            'type' => 'line',
            'title' => "Monthly Revenue (last {$months} months)",
            'labels' => $rows->pluck('month')->all(),
            'datasets' => [
                ['label' => 'Revenue ($)', 'data' => $rows->pluck('revenue')->map(fn ($v) => round((float) $v, 2))->all()],
            ],
        ]);

        $lines = [];

        foreach ($rows as $row) {
            $revenue = number_format((float) $row->revenue, 2);
            $lines[] = "{$row->month}: \${$revenue} revenue from {$row->orders_count} orders";
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'months' => $schema->integer(),
        ];
    }
}
